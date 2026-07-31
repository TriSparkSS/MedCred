<?php

namespace App\Services;

use App\Models\EmailAttachment;
use App\Models\EmailMessage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Message;

class ImapMailboxService
{
    public function __construct(
        protected MailSettingsService $mailSettings,
        protected CredentialingEmailService $emailService,
        protected EmailMatchingService $matchingService,
    ) {}

    /**
     * @return array{imported: int, skipped: int, errors: array<int, string>}
     */
    public function sync(): array
    {
        if (! $this->mailSettings->isImapConfigured()) {
            throw new \RuntimeException('IMAP is not configured. Enable IMAP in Admin → Settings → Mail.');
        }

        $settings = $this->mailSettings->getSettings();
        $result = ['imported' => 0, 'skipped' => 0, 'errors' => []];

        $folders = [
            [
                'folder' => $settings['imap_folder'],
                'direction' => 'inbound',
                'uid_key' => MailSettingsService::KEY_IMAP_LAST_UID,
                'last_uid' => $settings['imap_last_uid'],
            ],
        ];

        if ($settings['imap_sent_enabled'] && filled($settings['imap_sent_folder'])) {
            $folders[] = [
                'folder' => $settings['imap_sent_folder'],
                'direction' => 'outbound',
                'uid_key' => MailSettingsService::KEY_IMAP_SENT_LAST_UID,
                'last_uid' => $settings['imap_sent_last_uid'],
            ];
        }

        $client = (new ClientManager)->make($this->mailSettings->imapClientConfig());

        try {
            $client->connect();

            foreach ($folders as $folderConfig) {
                $folderResult = $this->syncFolder($client, $folderConfig);
                $result['imported'] += $folderResult['imported'];
                $result['skipped'] += $folderResult['skipped'];
                $result['errors'] = array_merge($result['errors'], $folderResult['errors']);
            }
        } finally {
            try {
                $client->disconnect();
            } catch (\Throwable) {
            }
        }

        return $result;
    }

    /**
     * @param  array{folder: string, direction: string, uid_key: string, last_uid: int}  $folderConfig
     * @return array{imported: int, skipped: int, errors: array<int, string>}
     */
    protected function syncFolder(Client $client, array $folderConfig): array
    {
        $result = ['imported' => 0, 'skipped' => 0, 'errors' => []];
        $maxUid = $folderConfig['last_uid'];
        $folder = $client->getFolder($folderConfig['folder']);

        if ($folderConfig['last_uid'] > 0) {
            $messages = $folder->messages()->all()->setFetchOrder('asc')->get()
                ->filter(fn (Message $m) => (int) $m->getUid() > $folderConfig['last_uid']);
        } else {
            $since = now()->subDays(30);
            $messages = $folder->messages()->since($since)->get();
        }

        foreach ($messages as $imapMessage) {
            try {
                $uid = (int) $imapMessage->getUid();
                if ($uid > $maxUid) {
                    $maxUid = $uid;
                }

                $parsed = $this->parseMessage($imapMessage);

                if ($this->isDuplicate($parsed)) {
                    $result['skipped']++;

                    continue;
                }

                $threadData = $this->resolveThread($parsed);
                $parsed = array_merge($parsed, $threadData);

                if ($folderConfig['direction'] === 'outbound') {
                    $emailMessage = $this->emailService->logOutboundFromImap($parsed);
                } else {
                    $emailMessage = $this->emailService->logInbound($parsed);
                }

                $this->storeAttachments($imapMessage, $emailMessage);

                $result['imported']++;
            } catch (\Throwable $e) {
                $result['errors'][] = $folderConfig['folder'] . ': ' . $e->getMessage();
            }
        }

        if ($maxUid > $folderConfig['last_uid']) {
            $this->mailSettings->setImapLastSyncForFolder($folderConfig['uid_key'], $maxUid);
        } elseif ($result['imported'] > 0) {
            $this->mailSettings->setImapLastSyncForFolder($folderConfig['uid_key'], $folderConfig['last_uid'] ?: $maxUid);
        }

        return $result;
    }

    protected function parseMessage(Message $imapMessage): array
    {
        $from = $imapMessage->getFrom()->first();
        $fromAddress = is_object($from) && isset($from->mail) ? $from->mail : (string) $from;

        $to = $imapMessage->getTo()->first();
        $toAddress = is_object($to) && isset($to->mail) ? $to->mail : (string) $to;
        if (blank($toAddress)) {
            $toAddress = config('credentialing.mailbox.from_address');
        }

        $messageId = $this->normalizeMessageId($this->headerValue($imapMessage->getMessageId()));
        $inReplyTo = $this->normalizeMessageId($this->headerValue($imapMessage->getInReplyTo()));

        $referenceHeaders = $imapMessage->getReferences();
        $references = collect(is_iterable($referenceHeaders) ? $referenceHeaders : [])
            ->map(fn ($ref) => $this->normalizeMessageId($this->headerValue($ref)))
            ->filter()
            ->implode(' ');

        $body = $imapMessage->getTextBody() ?: strip_tags((string) $imapMessage->getHTMLBody());
        $body = trim(html_entity_decode((string) $body, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $messageDate = $imapMessage->getDate()?->toDate() ?? now();

        return [
            'message_id' => $messageId,
            'external_message_id' => $messageId,
            'in_reply_to' => $inReplyTo,
            'references' => $references ?: null,
            'imap_uid' => (int) $imapMessage->getUid(),
            'from_address' => $fromAddress,
            'to_address' => $toAddress,
            'subject' => (string) $imapMessage->getSubject(),
            'body' => $body,
            'received_at' => $messageDate,
            'sent_at' => $messageDate,
        ];
    }

    protected function headerValue(mixed $header): ?string
    {
        if ($header === null) {
            return null;
        }

        if (is_string($header)) {
            return $header;
        }

        if (is_object($header) && method_exists($header, 'first')) {
            $first = $header->first();

            return $first !== null ? (string) $first : null;
        }

        return (string) $header;
    }

    protected function isDuplicate(array $parsed): bool
    {
        $normalized = $this->normalizeMessageIdForComparison($parsed['message_id'] ?? null);

        if (blank($normalized)) {
            return false;
        }

        $bracketed = '<' . $normalized . '>';

        return EmailMessage::where('message_id', $normalized)
            ->orWhere('message_id', $bracketed)
            ->orWhere('external_message_id', $normalized)
            ->orWhere('external_message_id', $bracketed)
            ->exists();
    }

    protected function normalizeMessageIdForComparison(?string $id): ?string
    {
        if (blank($id)) {
            return null;
        }

        return trim($id, '<>');
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
            $bracketed = $normalized ? '<' . $normalized . '>' : null;

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
            'thread_id' => $match['case'] ? 'case-' . $match['case']->id : null,
        ];
    }

    protected function storeAttachments(Message $imapMessage, EmailMessage $emailMessage): void
    {
        $hasAttachments = false;

        foreach ($imapMessage->getAttachments() as $attachment) {
            $name = $attachment->getName() ?: 'attachment-' . Str::random(8);
            $path = 'email-attachments/' . $emailMessage->id . '/' . $name;

            Storage::disk('public')->put($path, $attachment->getContent());

            EmailAttachment::create([
                'email_message_id' => $emailMessage->id,
                'file_path' => $path,
                'original_name' => $name,
                'file_size' => strlen($attachment->getContent()),
                'mime_type' => $attachment->getMimeType(),
            ]);

            $hasAttachments = true;
        }

        if ($hasAttachments) {
            $emailMessage->update(['has_pending_attachments' => true]);
            $category = $this->matchingService->categorizeQueue($emailMessage->fresh());
            $emailMessage->update(['queue_category' => $category]);
        }
    }

    protected function normalizeMessageId(mixed $id): ?string
    {
        if (blank($id)) {
            return null;
        }

        $id = trim((string) $id);

        if (! str_starts_with($id, '<')) {
            $id = '<' . trim($id, '<>') . '>';
        }

        return $id;
    }
}
