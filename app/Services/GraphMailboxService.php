<?php

namespace App\Services;

use App\Data\MailFolderPage;
use App\Data\MailMessageDto;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GraphMailboxService
{
    public const CACHE_TTL_SECONDS = 45;

    public const LIST_SELECT = 'id,subject,from,toRecipients,ccRecipients,receivedDateTime,sentDateTime,hasAttachments,conversationId,internetMessageId,isRead';

    public const DETAIL_SELECT = 'id,internetMessageId,conversationId,subject,body,from,toRecipients,ccRecipients,receivedDateTime,sentDateTime,hasAttachments,internetMessageHeaders,isRead';

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

        if ($withAttachments && $dto->hasAttachments && $dto->attachments === []) {
            $attachments = $this->listAttachmentMeta($graphId);
            $merged = array_merge($raw, ['attachments' => $attachments]);

            return MailMessageDto::fromGraph($merged, $folder, $this->mailbox());
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
        $attachment = $this->graphGet(
            "/users/{$mailbox}/messages/{$messageSegment}/attachments/{$attachmentSegment}"
        );

        $odataType = (string) ($attachment['@odata.type'] ?? '');
        if (! str_contains($odataType, 'fileAttachment') || empty($attachment['contentBytes'])) {
            throw new \RuntimeException('Attachment is not downloadable.');
        }

        $content = base64_decode((string) $attachment['contentBytes'], true);
        if ($content === false) {
            throw new \RuntimeException('Failed to decode attachment.');
        }

        return [
            'name' => (string) ($attachment['name'] ?? 'attachment'),
            'contentType' => $attachment['contentType'] ?? 'application/octet-stream',
            'content' => $content,
        ];
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
        $payload = $this->graphGet("/users/{$mailbox}/messages/{$messageSegment}/attachments", [
            '$select' => 'id,name,size,contentType,@odata.type',
        ]);

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
                if ($dto->hasAttachments && $dto->attachments === []) {
                    $meta = $this->listAttachmentMeta($dto->id);
                    $dto = MailMessageDto::fromGraph(array_merge($raw, ['attachments' => $meta]), $folder, $mailbox);
                }
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
                        if ($dto->hasAttachments && $dto->attachments === []) {
                            $meta = $this->listAttachmentMeta($dto->id);
                            $dto = MailMessageDto::fromGraph(array_merge($raw, ['attachments' => $meta]), $folder, $mailbox);
                        }
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
}
