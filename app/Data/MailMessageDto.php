<?php

namespace App\Data;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class MailMessageDto
{
    /**
     * @param  list<array{id: string, name: string, size: int|null, contentType: string|null}>  $attachments
     */
    public function __construct(
        public readonly string $id,
        public readonly string $folder,
        public readonly string $direction,
        public readonly ?string $internetMessageId,
        public readonly ?string $conversationId,
        public readonly ?string $inReplyTo,
        public readonly ?string $references,
        public readonly string $subject,
        public readonly string $fromAddress,
        public readonly string $fromName,
        public readonly string $toAddress,
        public readonly ?string $ccAddress,
        public readonly ?string $bodyHtml,
        public readonly ?string $bodyText,
        public readonly bool $hasAttachments,
        public readonly array $attachments,
        public readonly ?CarbonInterface $date,
        public readonly bool $isRead = true,
    ) {}

    public static function fromGraph(array $graphMessage, string $folder, string $mailboxAddress): self
    {
        $fromAddress = (string) data_get($graphMessage, 'from.emailAddress.address', '');
        $fromName = (string) data_get($graphMessage, 'from.emailAddress.name', '');
        if ($fromName === '') {
            $fromName = $fromAddress;
        }

        $toRecipients = collect($graphMessage['toRecipients'] ?? [])
            ->map(fn ($r) => (string) data_get($r, 'emailAddress.address', ''))
            ->filter()
            ->values();
        $ccRecipients = collect($graphMessage['ccRecipients'] ?? [])
            ->map(fn ($r) => (string) data_get($r, 'emailAddress.address', ''))
            ->filter()
            ->values();

        $headers = collect($graphMessage['internetMessageHeaders'] ?? []);
        $inReplyTo = self::normalizeMessageId(
            $headers->first(fn ($h) => strcasecmp((string) ($h['name'] ?? ''), 'In-Reply-To') === 0)['value'] ?? null
        );
        $references = $headers->first(fn ($h) => strcasecmp((string) ($h['name'] ?? ''), 'References') === 0)['value'] ?? null;

        $bodyContent = (string) data_get($graphMessage, 'body.content', '');
        $bodyType = strtolower((string) data_get($graphMessage, 'body.contentType', 'text'));
        $bodyHtml = null;
        $bodyText = null;

        if ($bodyContent !== '') {
            if ($bodyType === 'html') {
                $bodyHtml = $bodyContent;
                $bodyText = self::htmlToText($bodyContent);
            } else {
                $bodyText = trim(html_entity_decode($bodyContent, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            }
        }

        $mailbox = strtolower(trim($mailboxAddress));
        $fromLower = strtolower($fromAddress);
        $direction = $folder === 'sentitems' || ($mailbox !== '' && $fromLower === $mailbox)
            ? 'outbound'
            : 'inbound';

        $dateRaw = $graphMessage['receivedDateTime'] ?? $graphMessage['sentDateTime'] ?? null;
        $date = $dateRaw ? Carbon::parse($dateRaw) : null;

        $attachments = [];
        foreach ($graphMessage['attachments'] ?? [] as $attachment) {
            $odataType = (string) ($attachment['@odata.type'] ?? '');
            if (! str_contains($odataType, 'fileAttachment')) {
                continue;
            }
            $attachments[] = [
                'id' => (string) ($attachment['id'] ?? ''),
                'name' => (string) ($attachment['name'] ?? 'attachment'),
                'size' => isset($attachment['size']) ? (int) $attachment['size'] : null,
                'contentType' => $attachment['contentType'] ?? null,
            ];
        }

        return new self(
            id: (string) ($graphMessage['id'] ?? ''),
            folder: $folder,
            direction: $direction,
            internetMessageId: self::normalizeMessageId($graphMessage['internetMessageId'] ?? null),
            conversationId: filled($graphMessage['conversationId'] ?? null) ? (string) $graphMessage['conversationId'] : null,
            inReplyTo: $inReplyTo,
            references: filled($references) ? trim((string) $references) : null,
            subject: (string) ($graphMessage['subject'] ?? '(no subject)'),
            fromAddress: $fromAddress,
            fromName: $fromName,
            toAddress: $toRecipients->implode(', '),
            ccAddress: $ccRecipients->isNotEmpty() ? $ccRecipients->implode(', ') : null,
            bodyHtml: $bodyHtml,
            bodyText: $bodyText,
            hasAttachments: (bool) ($graphMessage['hasAttachments'] ?? false) || $attachments !== [],
            attachments: $attachments,
            date: $date,
            isRead: (bool) ($graphMessage['isRead'] ?? true),
        );
    }

    public function displayBody(): string
    {
        if (filled($this->bodyText)) {
            return $this->bodyText;
        }

        if (filled($this->bodyHtml)) {
            return self::htmlToText($this->bodyHtml);
        }

        return '';
    }

    public function fromInitials(): string
    {
        $name = trim($this->fromName ?: $this->fromAddress);
        $parts = preg_split('/\s+/', $name) ?: [];
        if (count($parts) >= 2) {
            return strtoupper(mb_substr($parts[0], 0, 1).mb_substr($parts[1], 0, 1));
        }

        return strtoupper(mb_substr($name, 0, 2));
    }

    public function normalizedMessageId(): ?string
    {
        if (blank($this->internetMessageId)) {
            return null;
        }

        return trim($this->internetMessageId, '<>');
    }

    public static function normalizeMessageId(mixed $id): ?string
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

    public static function htmlToText(string $html): string
    {
        $text = trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $text = preg_replace("/[ \t]+\n/", "\n", $text) ?? $text;

        return preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
    }
}
