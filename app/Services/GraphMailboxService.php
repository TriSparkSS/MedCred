<?php

namespace App\Services;

use App\Data\MailFolderPage;
use App\Data\MailMessageDto;
use Illuminate\Http\Client\Response as HttpResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GraphMailboxService
{
    public const CACHE_TTL_SECONDS = 45;

    public const LIST_SELECT = 'id,subject,from,toRecipients,ccRecipients,receivedDateTime,sentDateTime,hasAttachments,conversationId,internetMessageId,isRead';

    public const DETAIL_SELECT = 'id,internetMessageId,conversationId,subject,body,from,toRecipients,ccRecipients,receivedDateTime,sentDateTime,hasAttachments,internetMessageHeaders,isRead';

    public const ATTACHMENT_SELECT = 'id,name,size,contentType,isInline,microsoft.graph.fileAttachment/contentId';

    public const ATTACHMENT_META_SELECT = 'id,name,contentType,size,isInline';

    /**
     * contentId lives on the fileAttachment subtype, and tenants disagree about which
     * $select expressions are legal for a polymorphic attachment collection. Try the
     * cheap variants first and fall back to no $select at all, which always works
     * (contentBytes is stripped afterwards so nothing heavy is kept in memory).
     *
     * @var list<string|null>
     */
    public const ATTACHMENT_SELECT_VARIANTS = [
        'id,name,size,contentType,isInline,contentId',
        self::ATTACHMENT_SELECT,
        self::ATTACHMENT_META_SELECT,
        null,
    ];

    /**
     * Per-request memo so a thread render does not re-list the same attachments.
     *
     * @var array<string, list<array<string, mixed>>>
     */
    protected array $attachmentMetaCache = [];

    public function __construct(
        protected MicrosoftGraphTokenService $tokenService,
    ) {}

    public function isConfigured(): bool
    {
        return $this->tokenService->isConfigured();
    }

    public function clearCache(): void
    {
        Cache::forget($this->folderCountCacheKey('inbox'));
        Cache::forget($this->folderCountCacheKey('sentitems'));

        // Bust common list pages (1–20) for inbox/sent with empty search.
        foreach (['inbox', 'sentitems'] as $folder) {
            for ($page = 1; $page <= 20; $page++) {
                Cache::forget($this->listCacheKey($folder, $page, 15, null));
                Cache::forget($this->listCacheKey($folder, $page, 25, null));
            }
        }
    }

    /**
     * @return array{success: bool, mailbox: string, folders: array<int, array{folder: string, total_count: int}>}
     */
    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            $missing = implode(', ', $this->tokenService->missingConfigKeys());

            throw new \RuntimeException('Microsoft Graph is not configured. Set in .env: '.$missing);
        }

        $mailbox = $this->mailbox();
        $folders = ['inbox', 'sentitems'];
        $folderResults = [];

        foreach ($folders as $folder) {
            $response = $this->graphGet("/users/{$mailbox}/mailFolders/{$folder}", [
                '$select' => 'displayName,totalItemCount',
            ]);

            $folderResults[] = [
                'folder' => $response['displayName'] ?? $folder,
                'total_count' => (int) ($response['totalItemCount'] ?? 0),
            ];
        }

        return [
            'success' => true,
            'mailbox' => $mailbox,
            'folders' => $folderResults,
        ];
    }

    public function folderTotal(string $folder): int
    {
        $this->assertConfigured();
        $folder = $this->normalizeFolder($folder);

        return (int) Cache::remember(
            $this->folderCountCacheKey($folder),
            self::CACHE_TTL_SECONDS,
            function () use ($folder) {
                $response = $this->graphGet("/users/{$this->mailbox()}/mailFolders/{$folder}", [
                    '$select' => 'totalItemCount',
                ]);

                return (int) ($response['totalItemCount'] ?? 0);
            }
        );
    }

    public function listFolder(string $folder, int $page = 1, int $perPage = 15, ?string $search = null): MailFolderPage
    {
        $this->assertConfigured();
        $folder = $this->normalizeFolder($folder);
        $page = max(1, $page);
        $perPage = max(1, min(50, $perPage));
        $search = filled($search) ? trim($search) : null;

        $cacheKey = $this->listCacheKey($folder, $page, $perPage, $search);

        /** @var array{items: list<array<string, mixed>>, total: int} $cached */
        $cached = Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($folder, $page, $perPage, $search) {
            return $this->fetchFolderPage($folder, $page, $perPage, $search);
        });

        $mailbox = $this->mailbox();
        $items = collect($cached['items'])
            ->map(fn (array $raw) => MailMessageDto::fromGraph($raw, $folder, $mailbox));

        return new MailFolderPage(
            items: $items,
            page: $page,
            perPage: $perPage,
            total: (int) $cached['total'],
            folder: $folder,
            search: $search,
        );
    }

    public function getMessage(string $graphId, ?string $folderHint = null, bool $withAttachments = true): MailMessageDto
    {
        $this->assertConfigured();
        $raw = $this->fetchMessage($graphId);
        $folder = $folderHint ? $this->normalizeFolder($folderHint) : $this->inferFolder($raw);
        $dto = MailMessageDto::fromGraph($raw, $folder, $this->mailbox());

        if ($withAttachments) {
            return $this->ensureAttachmentMetadata($raw, $dto, $folder, $graphId);
        }

        return $dto;
    }

    /**
     * @return Collection<int, MailMessageDto>
     */
    public function getConversation(?string $conversationId, string $anchorGraphId, ?string $folderHint = null): Collection
    {
        $this->assertConfigured();
        $anchor = $this->getMessage($anchorGraphId, $folderHint, true);

        $messages = collect();

        if (filled($conversationId ?: $anchor->conversationId)) {
            $cid = $conversationId ?: $anchor->conversationId;
            try {
                $messages = $this->fetchByConversationId((string) $cid);
            } catch (\Throwable) {
                // Fall back to header-based / standalone threading below.
                $messages = collect();
            }
        }

        if ($messages->isEmpty()) {
            try {
                $messages = $this->fetchByThreadHeaders($anchor);
            } catch (\Throwable) {
                $messages = collect();
            }
        }

        if ($messages->isEmpty() || ! $messages->contains(fn (MailMessageDto $m) => $m->id === $anchor->id)) {
            $messages = $messages->push($anchor)->unique(fn (MailMessageDto $m) => $m->id);
        }

        return $messages
            ->sortBy(fn (MailMessageDto $m) => $m->date?->timestamp ?? 0)
            ->values();
    }

    /**
     * @return array{name: string, contentType: string|null, content: string}
     */
    public function downloadAttachment(string $messageId, string $attachmentId): array
    {
        $this->assertConfigured();
        $mailbox = $this->mailbox();
        $messageSegment = $this->encodeGraphPathSegment($messageId);
        $attachmentSegment = $this->encodeGraphPathSegment($attachmentId);
        $attachmentPath = "/users/{$mailbox}/messages/{$messageSegment}/attachments/{$attachmentSegment}";

        $attachment = null;

        try {
            $attachment = $this->graphGet($attachmentPath, [
                '$select' => 'id,name,contentType,size,isInline,microsoft.graph.fileAttachment/contentBytes',
            ]);
        } catch (\RuntimeException) {
            $attachment = $this->graphGet($attachmentPath);
        }

        $odataType = (string) ($attachment['@odata.type'] ?? '');

        if (str_contains($odataType, 'referenceAttachment')) {
            throw new \RuntimeException('This attachment is stored in cloud storage. Use the source link instead.');
        }

        if ($odataType !== '' && ! str_contains($odataType, 'fileAttachment')) {
            throw new \RuntimeException('This attachment type cannot be downloaded.');
        }

        $name = (string) ($attachment['name'] ?? 'attachment');
        $contentType = (string) ($attachment['contentType'] ?? 'application/octet-stream');
        $content = null;

        if (! empty($attachment['contentBytes'])) {
            try {
                $content = $this->decodeContentBytes((string) $attachment['contentBytes']);
            } catch (\RuntimeException) {
                $content = null;
            }
        }

        if ($content === null || $content === '' || $this->looksLikeCorruptOfficeFile($name, $content)) {
            $binary = $this->graphGetBinary($attachmentPath.'/$value');

            if ($binary === '') {
                throw new \RuntimeException('Failed to retrieve attachment content.');
            }

            // Some gateways mistakenly return base64 text from /$value.
            if ($this->looksLikeBase64Payload($binary) && $this->looksLikeCorruptOfficeFile($name, $binary)) {
                try {
                    $decoded = $this->decodeContentBytes($binary);
                    if (! $this->looksLikeCorruptOfficeFile($name, $decoded)) {
                        $binary = $decoded;
                    }
                } catch (\RuntimeException) {
                    // Keep raw bytes.
                }
            }

            $content = $binary;
        }

        if ($content === '' || $this->looksLikeHtmlOrJsonError($content)) {
            throw new \RuntimeException('Attachment download returned invalid content.');
        }

        if ($this->looksLikeCorruptOfficeFile($name, $content)) {
            throw new \RuntimeException('Attachment content appears corrupted. Please try again.');
        }

        return [
            'name' => $name,
            'contentType' => $contentType,
            'content' => $content,
        ];
    }

    protected function decodeContentBytes(string $contentBytes): string
    {
        $normalized = preg_replace('/\s+/', '', $contentBytes) ?? $contentBytes;
        $content = base64_decode($normalized, true);

        if ($content === false || $content === '') {
            throw new \RuntimeException('Failed to decode attachment.');
        }

        return $content;
    }

    protected function looksLikeBase64Payload(string $content): bool
    {
        $trimmed = preg_replace('/\s+/', '', $content) ?? '';

        return strlen($trimmed) >= 32
            && strlen($trimmed) % 4 === 0
            && preg_match('/^[A-Za-z0-9+\/]+=*$/', $trimmed) === 1;
    }

    protected function looksLikeHtmlOrJsonError(string $content): bool
    {
        $prefix = ltrim(substr($content, 0, 200));

        return str_starts_with($prefix, '<!DOCTYPE')
            || str_starts_with($prefix, '<html')
            || str_starts_with($prefix, '{')
            || str_starts_with($prefix, '[');
    }

    protected function looksLikeCorruptOfficeFile(string $name, string $content): bool
    {
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if (! in_array($extension, ['xlsx', 'docx', 'pptx', 'xls', 'doc'], true)) {
            return false;
        }

        if ($content === '') {
            return true;
        }

        // OOXML formats are ZIP packages and must start with PK.
        if (in_array($extension, ['xlsx', 'docx', 'pptx'], true)) {
            return ! str_starts_with($content, "PK");
        }

        // Legacy OLE Compound File magic: D0 CF 11 E0
        return ! str_starts_with($content, "\xD0\xCF\x11\xE0");
    }

    protected function ensureAttachmentMetadata(array $raw, MailMessageDto $dto, string $folder, string $graphId): MailMessageDto
    {
        $bodyContent = (string) data_get($raw, 'body.content', '');
        $needsAttachments = (
            $dto->hasAttachments
            || stripos($bodyContent, 'cid:') !== false
            || stripos($bodyContent, '<img') !== false
        ) && $dto->attachments === [];

        if (! $needsAttachments) {
            return $dto;
        }

        $meta = $this->listAttachmentMeta($graphId);

        return MailMessageDto::fromGraph(array_merge($raw, ['attachments' => $meta]), $folder, $this->mailbox());
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    protected function fetchFolderPage(string $folder, int $page, int $perPage, ?string $search): array
    {
        $mailbox = $this->mailbox();
        $skip = ($page - 1) * $perPage;
        $total = $this->folderTotal($folder);

        $query = [
            '$orderby' => $folder === 'sentitems' ? 'sentDateTime desc' : 'receivedDateTime desc',
            '$top' => (string) $perPage,
            '$skip' => (string) $skip,
            '$select' => self::LIST_SELECT,
            '$count' => 'true',
        ];

        if (filled($search)) {
            // Graph $search cannot combine with $filter/$orderby on some tenants; use $search alone.
            unset($query['$orderby'], $query['$count']);
            $query['$search'] = '"'.str_replace('"', '', $search).'"';
        }

        $headers = filled($search) ? ['ConsistencyLevel' => 'eventual'] : [];
        $payload = $this->graphGet(
            "/users/{$mailbox}/mailFolders/{$folder}/messages",
            $query,
            $headers
        );

        if (isset($payload['@odata.count'])) {
            $total = (int) $payload['@odata.count'];
        } elseif (filled($search)) {
            $total = count($payload['value'] ?? []) + $skip;
            if (! empty($payload['@odata.nextLink'])) {
                $total += $perPage;
            }
        }

        return [
            'items' => $payload['value'] ?? [],
            'total' => $total,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function fetchMessage(string $messageId): array
    {
        $mailbox = $this->mailbox();
        $messageSegment = $this->encodeGraphPathSegment($messageId);

        return $this->graphGet("/users/{$mailbox}/messages/{$messageSegment}", [
            '$select' => self::DETAIL_SELECT,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function listAttachmentMeta(string $messageId): array
    {
        $mailbox = $this->mailbox();
        $messageSegment = $this->encodeGraphPathSegment($messageId);

        try {
            $payload = $this->graphGet("/users/{$mailbox}/messages/{$messageSegment}/attachments", [
                '$select' => self::ATTACHMENT_SELECT,
            ]);
        } catch (\RuntimeException) {
            // Some tenants reject typed contentId selects; fall back without it.
            $payload = $this->graphGet("/users/{$mailbox}/messages/{$messageSegment}/attachments", [
                '$select' => 'id,name,size,contentType,isInline',
            ]);
        }

        return $payload['value'] ?? [];
    }

    /**
     * Encode Graph resource ids for path segments (ids may contain / + =).
     */
    protected function encodeGraphPathSegment(string $id): string
    {
        return rawurlencode($id);
    }

    /**
     * @return Collection<int, MailMessageDto>
     */
    protected function fetchByConversationId(string $conversationId): Collection
    {
        $mailbox = $this->mailbox();
        $escaped = str_replace("'", "''", $conversationId);
        $items = collect();

        // Do not combine conversationId $filter with $orderby — Graph returns
        // "The restriction or sort order is too complex for this operation."
        // Sort chronologically in PHP after fetch instead.
        $url = $this->graphUrl("/users/{$mailbox}/messages");
        $query = [
            '$filter' => "conversationId eq '{$escaped}'",
            '$select' => self::DETAIL_SELECT,
            '$top' => '50',
        ];

        $isFirst = true;
        while ($url) {
            $payload = $this->graphGetAbsolute($url, $isFirst ? $query : []);
            $isFirst = false;

            foreach ($payload['value'] ?? [] as $raw) {
                $folder = $this->inferFolder($raw);
                $dto = MailMessageDto::fromGraph($raw, $folder, $mailbox);
                $dto = $this->ensureAttachmentMetadata($raw, $dto, $folder, $dto->id);
                $items->push($dto);
            }

            $url = $payload['@odata.nextLink'] ?? null;
            if ($items->count() >= 100) {
                break;
            }
        }

        return $items->unique(fn (MailMessageDto $m) => $m->id)->values();
    }

    /**
     * @return Collection<int, MailMessageDto>
     */
    protected function fetchByThreadHeaders(MailMessageDto $anchor): Collection
    {
        $mailbox = $this->mailbox();
        $ids = collect();

        if ($anchor->internetMessageId) {
            $ids->push($anchor->normalizedMessageId());
        }
        if ($anchor->inReplyTo) {
            $ids->push(trim($anchor->inReplyTo, '<>'));
        }
        if ($anchor->references) {
            foreach (preg_split('/\s+/', $anchor->references) ?: [] as $ref) {
                $normalized = trim($ref, '<>');
                if ($normalized !== '') {
                    $ids->push($normalized);
                }
            }
        }

        $ids = $ids->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return collect([$anchor]);
        }

        $found = collect([$anchor]);

        foreach ($ids->take(10) as $messageId) {
            try {
                $escaped = str_replace("'", "''", $messageId);
                // Search both bracketed and bare forms.
                foreach ([$escaped, '<'.$escaped.'>'] as $variant) {
                    $payload = $this->graphGet("/users/{$mailbox}/messages", [
                        '$filter' => "internetMessageId eq '".str_replace("'", "''", $variant)."'",
                        '$select' => self::DETAIL_SELECT,
                        '$top' => '5',
                    ]);

                    foreach ($payload['value'] ?? [] as $raw) {
                        $folder = $this->inferFolder($raw);
                        $dto = MailMessageDto::fromGraph($raw, $folder, $mailbox);
                        $dto = $this->ensureAttachmentMetadata($raw, $dto, $folder, $dto->id);
                        $found->push($dto);
                    }
                }
            } catch (\Throwable) {
                // Ignore lookup failures for individual header IDs.
            }
        }

        return $found->unique(fn (MailMessageDto $m) => $m->id)->values();
    }

    protected function inferFolder(array $graphMessage): string
    {
        $mailbox = strtolower($this->mailbox());
        $from = strtolower((string) data_get($graphMessage, 'from.emailAddress.address', ''));

        return ($mailbox !== '' && $from === $mailbox) ? 'sentitems' : 'inbox';
    }

    public function normalizeFolder(string $folder): string
    {
        $folder = strtolower(trim($folder));

        return match ($folder) {
            'sent', 'sentitems', 'sent items' => 'sentitems',
            default => 'inbox',
        };
    }

    protected function assertConfigured(): void
    {
        if (! $this->isConfigured()) {
            $missing = implode(', ', $this->tokenService->missingConfigKeys());

            throw new \RuntimeException('Microsoft Graph is not configured. Set in .env: '.$missing);
        }
    }

    protected function mailbox(): string
    {
        return (string) config('services.microsoft_graph.mailbox');
    }

    protected function listCacheKey(string $folder, int $page, int $perPage, ?string $search): string
    {
        return 'graph.mail.list.'.md5($folder.'|'.$page.'|'.$perPage.'|'.($search ?? ''));
    }

    protected function folderCountCacheKey(string $folder): string
    {
        return 'graph.mail.count.'.$folder;
    }

    protected function graphUrl(string $path): string
    {
        return 'https://graph.microsoft.com/v1.0'.(str_starts_with($path, '/') ? $path : '/'.$path);
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, string>  $headers
     * @return array<string, mixed>
     */
    protected function graphGet(string $path, array $query = [], array $headers = []): array
    {
        return $this->graphGetAbsolute($this->graphUrl($path), $query, $headers);
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, string>  $headers
     * @return array<string, mixed>
     */
    protected function graphGetAbsolute(string $url, array $query = [], array $headers = []): array
    {
        $request = Http::withToken($this->tokenService->getAccessToken())
            ->acceptJson()
            ->timeout(60)
            ->withHeaders($headers);

        $response = $query === []
            ? $request->get($url)
            : $request->get($url, $query);

        if ($response->status() === 401) {
            $this->tokenService->forgetCachedToken();
            $request = Http::withToken($this->tokenService->getAccessToken())
                ->acceptJson()
                ->timeout(60)
                ->withHeaders($headers);
            $response = $query === []
                ? $request->get($url)
                : $request->get($url, $query);
        }

        if (! $response->successful()) {
            $error = $response->json('error.message')
                ?? $response->json('error_description')
                ?? $response->body();

            throw new \RuntimeException('Microsoft Graph request failed: '.$error);
        }

        return $response->json() ?? [];
    }

    protected function graphGetBinary(string $path): string
    {
        $url = $this->graphUrl($path);

        $response = $this->binaryRequest()->get($url);

        if ($response->status() === 401) {
            $this->tokenService->forgetCachedToken();
            $response = $this->binaryRequest()->get($url);
        }

        if (! $response->successful()) {
            $error = $response->json('error.message')
                ?? $response->json('error_description')
                ?? $response->body();

            throw new \RuntimeException('Microsoft Graph request failed: '.$error);
        }

        $contentType = strtolower((string) $response->header('Content-Type'));
        $body = $response->body();

        // Guard against JSON error payloads returned with unexpected status handling.
        if (str_contains($contentType, 'application/json') || $this->looksLikeHtmlOrJsonError($body)) {
            $message = data_get(json_decode($body, true) ?: [], 'error.message', 'Invalid binary attachment response.');

            throw new \RuntimeException('Microsoft Graph request failed: '.$message);
        }

        return $body;
    }

    protected function binaryRequest(): \Illuminate\Http\Client\PendingRequest
    {
        // Keep gzip/deflate decoding enabled so Office files are not saved compressed.
        return Http::withToken($this->tokenService->getAccessToken())
            ->timeout(120)
            ->withHeaders([
                'Accept' => '*/*',
                'Accept-Encoding' => 'gzip, deflate',
            ]);
    }
}
