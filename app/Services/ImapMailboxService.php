<?php

namespace App\Services;

use App\Models\EmailAttachment;
use App\Models\EmailMessage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
        $maxUid = $settings['imap_last_uid'];

        $client = (new ClientManager)->make($this->mailSettings->imapClientConfig());

        try {
            $client->connect();
            $folder = $client->getFolder($settings['imap_folder']);

            $query = $folder->messages()->all()->setFetchOrder('asc');

            if ($settings['imap_last_uid'] > 0) {
                $messages = $query->get()->filter(fn (Message $m) => (int) $m->getUid() > $settings['imap_last_uid']);
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

                    $emailMessage = $this->emailService->logInbound($parsed);
                    $this->storeAttachments($imapMessage, $emailMessage);

                    $result['imported']++;
                } catch (\Throwable $e) {
                    $result['errors'][] = $e->getMessage();
                }
            }

            if ($maxUid > $settings['imap_last_uid']) {
                $this->mailSettings->setImapLastSync($maxUid);
            } elseif ($result['imported'] > 0) {
                $this->mailSettings->setImapLastSync($settings['imap_last_uid'] ?: $maxUid);
            }
        } finally {
            try {
                $client->disconnect();
            } catch (\Throwable) {
            }
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

        return [
            'message_id' => $messageId,
            'external_message_id' => $messageId,
            'in_reply_to' => $inReplyTo,
            'references' => $references ?: null,
            'imap_uid' => (int) $imapMessage->getUid(),
            'from_address' => $fromAddress,
            'to_address' => $toAddress,
            'subject' => (string) $imapMessage->getSubject(),
            'body' => trim($body),
            'received_at' => $imapMessage->getDate()?->toDate() ?? now(),
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
        if (blank($parsed['message_id'])) {
            return false;
        }

        return EmailMessage::where('message_id', $parsed['message_id'])
            ->orWhere('external_message_id', $parsed['message_id'])
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
            $parent = EmailMessage::where('message_id', $parentId)
                ->orWhere('external_message_id', $parentId)
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
