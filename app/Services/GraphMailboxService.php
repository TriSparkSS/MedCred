<?php

namespace App\Services;

use App\Models\EmailAttachment;
use App\Models\EmailMessage;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GraphMailboxService
{
    public const KEY_INBOX_SINCE = 'mail.graph.inbox_since';

    public const KEY_SENT_SINCE = 'mail.graph.sent_since';

    public function __construct(
        protected MicrosoftGraphTokenService $tokenService,
        protected MailSettingsService $mailSettings,
        protected CredentialingEmailService $emailService,
        protected EmailMatchingService $matchingService,
    ) {}

    public function isConfigured(): bool
    {
        return $this->tokenService->isConfigured();
    }

    /**
     * @return array{imported: int, skipped: int, errors: array<int, string>, has_more: bool}
     */
    public function sync(?int $maxMessagesPerFolder = null): array
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException(
                'Microsoft Graph is not configured. Set GRAPH_TENANT_ID, GRAPH_CLIENT_ID, GRAPH_CLIENT_SECRET, and GRAPH_MAILBOX in .env.'
            );
        }

        $maxMessagesPerFolder ??= (int) config('services.microsoft_graph.sync_batch_size', 50);
        $settings = $this->mailSettings->getSettings();
        $result = ['imported' => 0, 'skipped' => 0, 'errors' => [], 'has_more' => false];

        $folders = [
            [
                'folder' => 'inbox',
                'label' => 'Inbox',
                'direction' => 'inbound',
                'since_key' => self::KEY_INBOX_SINCE,
                'date_field' => 'receivedDateTime',
            ],
        ];

        if ($settings['imap_sent_enabled']) {
            $folders[] = [
                'folder' => 'sentitems',
                'label' => 'Sent Items',
                'direction' => 'outbound',
                'since_key' => self::KEY_SENT_SINCE,
                'date_field' => 'sentDateTime',
            ];
        }

        foreach ($folders as $folderConfig) {
            $folderResult = $this->syncFolder($folderConfig, $maxMessagesPerFolder);
            $result['imported'] += $folderResult['imported'];
            $result['skipped'] += $folderResult['skipped'];
            $result['errors'] = array_merge($result['errors'], $folderResult['errors']);

            if ($folderResult['has_more']) {
                $result['has_more'] = true;
            }
        }

        Setting::set(MailSettingsService::KEY_IMAP_LAST_SYNC_AT, now()->toIso8601String());

        return $result;
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

    /**
     * @param  array{folder: string, label: string, direction: string, since_key: string, date_field: string}  $folderConfig
     * @return array{imported: int, skipped: int, errors: array<int, string>, has_more: bool}
     */
    protected function syncFolder(array $folderConfig, int $maxMessages): array
    {
        $result = ['imported' => 0, 'skipped' => 0, 'errors' => [], 'has_more' => false];
        $mailbox = $this->mailbox();
        $since = Setting::getDecrypted($folderConfig['since_key']);
        $dateField = $folderConfig['date_field'];

        if (blank($since)) {
            $since = now()->subDays(30)->utc()->format('Y-m-d\TH:i:s\Z');
        }

        $filter = "{$dateField} ge {$since}";
        $url = $this->graphUrl("/users/{$mailbox}/mailFolders/{$folderConfig['folder']}/messages");
        $query = [
            '$filter' => $filter,
            '$orderby' => "{$dateField} asc",
            '$top' => (string) min($maxMessages, 50),
            '$select' => 'id,internetMessageId,receivedDateTime,sentDateTime,hasAttachments',
        ];

        $latestSeen = $since;
        $isFirstPage = true;
        $processed = 0;

        while ($url) {
            $payload = $this->graphGetAbsolute($url, $isFirstPage ? $query : []);
            $isFirstPage = false;
            $messages = $payload['value'] ?? [];
            $existingIds = $this->existingMessageIdLookup($messages);

            foreach ($messages as $graphMessage) {
                if ($processed >= $maxMessages) {
                    $result['has_more'] = true;
                    break 2;
                }

                try {
                    $messageDate = $graphMessage[$dateField] ?? null;
                    if ($messageDate && strcmp((string) $messageDate, (string) $latestSeen) > 0) {
                        $latestSeen = $messageDate;
                    }

                    $normalizedId = $this->normalizeMessageIdForComparison($graphMessage['internetMessageId'] ?? null);

                    if ($normalizedId && isset($existingIds[$normalizedId])) {
                        $result['skipped']++;
                        $processed++;

                        continue;
                    }

                    $fullMessage = $this->fetchMessage((string) $graphMessage['id']);
                    $parsed = $this->parseMessage($fullMessage);

                    if ($this->isDuplicate($parsed)) {
                        $result['skipped']++;
                        $processed++;

                        continue;
                    }

                    $threadData = $this->resolveThread($parsed);
                    $parsed = array_merge($parsed, $threadData);

                    if ($folderConfig['direction'] === 'outbound') {
                        $emailMessage = $this->emailService->logOutboundFromImap($parsed);
                    } else {
                        $emailMessage = $this->emailService->logInbound($parsed);
                    }

                    if (! empty($fullMessage['hasAttachments'])) {
                        $this->storeAttachments((string) $fullMessage['id'], $emailMessage);
                    }

                    $result['imported']++;
                    $processed++;
                } catch (\Throwable $e) {
                    $result['errors'][] = $folderConfig['label'].': '.$e->getMessage();
                    $processed++;
                }
            }

            $url = $payload['@odata.nextLink'] ?? null;

            if ($url && $processed >= $maxMessages) {
                $result['has_more'] = true;
                break;
            }
        }

        if (strcmp((string) $latestSeen, (string) $since) > 0 || $processed > 0) {
            $cursor = \Carbon\Carbon::parse($latestSeen)->utc()->addSecond()->format('Y-m-d\TH:i:s\Z');
            Setting::set($folderConfig['since_key'], $cursor);
        }

        return $result;
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @return array<string, true>
     */
    protected function existingMessageIdLookup(array $messages): array
    {
        $variants = [];

        foreach ($messages as $message) {
            $normalized = $this->normalizeMessageIdForComparison($message['internetMessageId'] ?? null);

            if (blank($normalized)) {
                continue;
            }

            $variants[] = $normalized;
            $variants[] = '<'.$normalized.'>';
        }

        if ($variants === []) {
            return [];
        }

        $existing = EmailMessage::query()
            ->where(function ($query) use ($variants) {
                $query->whereIn('message_id', $variants)
                    ->orWhereIn('external_message_id', $variants);
            })
            ->get(['message_id', 'external_message_id']);

        $lookup = [];

        foreach ($existing as $row) {
            foreach ([$row->message_id, $row->external_message_id] as $id) {
                if (filled($id)) {
                    $lookup[trim((string) $id, '<>')] = true;
                }
            }
        }

        return $lookup;
    }

    /**
     * @return array<string, mixed>
     */
    protected function fetchMessage(string $messageId): array
    {
        $mailbox = $this->mailbox();

        return $this->graphGet("/users/{$mailbox}/messages/{$messageId}", [
            '$select' => 'id,internetMessageId,conversationId,subject,body,from,toRecipients,receivedDateTime,sentDateTime,hasAttachments,internetMessageHeaders',
        ]);
    }

    protected function parseMessage(array $graphMessage): array
    {
        $fromAddress = data_get($graphMessage, 'from.emailAddress.address', '');
        $toAddress = data_get($graphMessage, 'toRecipients.0.emailAddress.address', '');

        if (blank($toAddress)) {
            $toAddress = config('credentialing.mailbox.from_address')
                ?: (string) config('services.microsoft_graph.mailbox');
        }

        $messageId = $this->normalizeMessageId($graphMessage['internetMessageId'] ?? null);
        $headers = collect($graphMessage['internetMessageHeaders'] ?? []);
        $inReplyTo = $this->normalizeMessageId(
            $headers->first(fn ($h) => strcasecmp((string) ($h['name'] ?? ''), 'In-Reply-To') === 0)['value'] ?? null
        );
        $references = $headers->first(fn ($h) => strcasecmp((string) ($h['name'] ?? ''), 'References') === 0)['value'] ?? null;

        $bodyContent = (string) data_get($graphMessage, 'body.content', '');
        $bodyType = strtolower((string) data_get($graphMessage, 'body.contentType', 'text'));
        $body = $bodyType === 'html' ? trim(strip_tags($bodyContent)) : trim($bodyContent);

        $receivedAt = isset($graphMessage['receivedDateTime'])
            ? \Carbon\Carbon::parse($graphMessage['receivedDateTime'])
            : now();
        $sentAt = isset($graphMessage['sentDateTime'])
            ? \Carbon\Carbon::parse($graphMessage['sentDateTime'])
            : $receivedAt;

        return [
            'message_id' => $messageId,
            'external_message_id' => $messageId ?: ($graphMessage['id'] ?? null),
            'in_reply_to' => $inReplyTo,
            'references' => filled($references) ? trim((string) $references) : null,
            'from_address' => (string) $fromAddress,
            'to_address' => (string) $toAddress,
            'subject' => (string) ($graphMessage['subject'] ?? ''),
            'body' => $body,
            'received_at' => $receivedAt,
            'sent_at' => $sentAt,
        ];
    }

    protected function storeAttachments(string $graphMessageId, EmailMessage $emailMessage): void
    {
        $mailbox = $this->mailbox();
        $payload = $this->graphGet("/users/{$mailbox}/messages/{$graphMessageId}/attachments");
        $hasAttachments = false;

        foreach ($payload['value'] ?? [] as $attachment) {
            $odataType = (string) ($attachment['@odata.type'] ?? '');

            if (! str_contains($odataType, 'fileAttachment') || empty($attachment['contentBytes'])) {
                continue;
            }

            $name = $attachment['name'] ?: 'attachment-'.Str::random(8);
            $content = base64_decode((string) $attachment['contentBytes'], true);

            if ($content === false) {
                continue;
            }

            $path = 'email-attachments/'.$emailMessage->id.'/'.$name;
            Storage::disk('public')->put($path, $content);

            EmailAttachment::create([
                'email_message_id' => $emailMessage->id,
                'file_path' => $path,
                'original_name' => $name,
                'file_size' => strlen($content),
                'mime_type' => $attachment['contentType'] ?? null,
            ]);

            $hasAttachments = true;
        }

        if ($hasAttachments) {
            $emailMessage->update(['has_pending_attachments' => true]);
            $category = $this->matchingService->categorizeQueue($emailMessage->fresh());
            $emailMessage->update(['queue_category' => $category]);
        }
    }

    protected function isDuplicate(array $parsed): bool
    {
        $normalized = $this->normalizeMessageIdForComparison($parsed['message_id'] ?? null);

        if (blank($normalized)) {
            return false;
        }

        $bracketed = '<'.$normalized.'>';

        return EmailMessage::where('message_id', $normalized)
            ->orWhere('message_id', $bracketed)
            ->orWhere('external_message_id', $normalized)
            ->orWhere('external_message_id', $bracketed)
            ->exists();
    }

    /**
     * @return array{credentialing_case_id: ?int, provider_id: ?int, thread_id: ?string}
     */
    protected function resolveThread(array $parsed): array
    {
        $parentIds = array_filter([
            $parsed['in_reply_to'],
            ...preg_split('/\s+/', (string) ($parsed['references'] ?? '')) ?: [],
        ]);

        foreach ($parentIds as $parentId) {
            $normalized = $this->normalizeMessageIdForComparison($parentId);
            $bracketed = $normalized ? '<'.$normalized.'>' : null;

            $parent = EmailMessage::query()
                ->when($normalized, function ($query) use ($normalized, $bracketed) {
                    $query->where(function ($q) use ($normalized, $bracketed) {
                        $q->where('message_id', $normalized)
                            ->orWhere('message_id', $bracketed)
                            ->orWhere('external_message_id', $normalized)
                            ->orWhere('external_message_id', $bracketed);
                    });
                })
                ->first();

            if ($parent) {
                return [
                    'credentialing_case_id' => $parent->credentialing_case_id,
                    'provider_id' => $parent->provider_id,
                    'thread_id' => $parent->thread_id,
                ];
            }
        }

        $match = $this->matchingService->match(
            $parsed['subject'],
            $parsed['body'],
            $parsed['from_address']
        );

        return [
            'credentialing_case_id' => $match['case']?->id,
            'provider_id' => $match['provider']?->id,
            'thread_id' => $match['case'] ? 'case-'.$match['case']->id : null,
        ];
    }

    protected function mailbox(): string
    {
        return (string) config('services.microsoft_graph.mailbox');
    }

    protected function graphUrl(string $path): string
    {
        return 'https://graph.microsoft.com/v1.0'.(str_starts_with($path, '/') ? $path : '/'.$path);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    protected function graphGet(string $path, array $query = []): array
    {
        return $this->graphGetAbsolute($this->graphUrl($path), $query);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    protected function graphGetAbsolute(string $url, array $query = []): array
    {
        $request = Http::withToken($this->tokenService->getAccessToken())
            ->acceptJson()
            ->timeout(60);

        $response = $query === []
            ? $request->get($url)
            : $request->get($url, $query);

        if ($response->status() === 401) {
            $this->tokenService->forgetCachedToken();
            $request = Http::withToken($this->tokenService->getAccessToken())
                ->acceptJson()
                ->timeout(60);
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

    protected function normalizeMessageId(mixed $id): ?string
    {
        if (blank($id)) {
            return null;
        }

        $id = trim((string) $id);

        if (! str_starts_with($id, '<')) {
            $id = '<'.trim($id, '<>').'>';
        }

        return $id;
    }

    protected function normalizeMessageIdForComparison(?string $id): ?string
    {
        if (blank($id)) {
            return null;
        }

        return trim($id, '<>');
    }
}
