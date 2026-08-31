<?php

namespace App\Data;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class MailMessageDto
{
    /**
     * @param  list<array{id: string, name: string, size: int|null, contentType: string|null, contentId: string|null, isInline: bool, kind: string, sourceUrl: string|null}>  $attachments
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

            if (str_contains($odataType, 'referenceAttachment')) {
                $attachments[] = [
                    'id' => (string) ($attachment['id'] ?? ''),
                    'name' => (string) ($attachment['name'] ?? 'attachment'),
                    'size' => isset($attachment['size']) ? (int) $attachment['size'] : null,
                    'contentType' => $attachment['contentType'] ?? null,
                    'contentId' => null,
                    'isInline' => false,
                    'kind' => 'reference',
                    'sourceUrl' => filled($attachment['sourceUrl'] ?? null) ? (string) $attachment['sourceUrl'] : null,
                ];

                continue;
            }

            if ($odataType !== '' && ! str_contains($odataType, 'fileAttachment')) {
                continue;
            }

            if (blank($attachment['id'] ?? null)) {
                continue;
            }

            $attachments[] = [
                'id' => (string) $attachment['id'],
                'name' => (string) ($attachment['name'] ?? 'attachment'),
                'size' => isset($attachment['size']) ? (int) $attachment['size'] : null,
                'contentType' => $attachment['contentType'] ?? null,
                'contentId' => filled($attachment['contentId'] ?? null) ? (string) $attachment['contentId'] : null,
                'isInline' => (bool) ($attachment['isInline'] ?? false),
                'kind' => 'file',
                'sourceUrl' => null,
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

    /**
     * Replace inline cid: image references with downloadable attachment URLs,
     * then sanitize HTML while preserving safe attributes (esp. img src).
     */
    public function bodyHtmlWithInlineAttachments(string $folder, string $encodedMessageId): ?string
    {
        if (blank($this->bodyHtml)) {
            return null;
        }

        $cidToUrl = [];
        $nameToUrl = [];

        foreach ($this->attachments as $attachment) {
            if (($attachment['kind'] ?? 'file') !== 'file' || blank($attachment['id'])) {
                continue;
            }

            $url = route('admin.email.attachment', [
                'folder' => $folder,
                'messageId' => $encodedMessageId,
                'attachmentId' => self::encodeGraphId($attachment['id']),
            ]);

            $name = strtolower(trim((string) ($attachment['name'] ?? '')));
            if ($name !== '') {
                $nameToUrl[$name] = $url;
            }

            if (blank($attachment['contentId'] ?? null)) {
                continue;
            }

            $contentId = trim((string) $attachment['contentId'], '<>');
            if ($contentId === '') {
                continue;
            }

            $cidToUrl[strtolower($contentId)] = $url;

            if (str_contains($contentId, '@')) {
                $cidToUrl[strtolower(explode('@', $contentId, 2)[0])] = $url;
            }
        }

        $html = $this->bodyHtml;

        if ($cidToUrl !== [] || $nameToUrl !== []) {
            $html = preg_replace_callback(
                '/\bcid:([^"\'>\s]+)/i',
                function (array $matches) use ($cidToUrl, $nameToUrl): string {
                    $cid = strtolower(trim(urldecode($matches[1]), '<>'));

                    if (isset($cidToUrl[$cid])) {
                        return $cidToUrl[$cid];
                    }

                    if (str_contains($cid, '@')) {
                        $base = strtolower(explode('@', $cid, 2)[0]);
                        if (isset($cidToUrl[$base])) {
                            return $cidToUrl[$base];
                        }
                        if (isset($nameToUrl[$base])) {
                            return $nameToUrl[$base];
                        }
                    }

                    if (isset($nameToUrl[$cid])) {
                        return $nameToUrl[$cid];
                    }

                    return $matches[0];
                },
                $html
            ) ?? $html;
        }

        // Last resort: map remaining cid: refs to unused inline image attachments in order.
        if (stripos($html, 'cid:') !== false) {
            $unusedUrls = [];
            foreach ($this->attachments as $attachment) {
                if (($attachment['kind'] ?? 'file') !== 'file' || blank($attachment['id'])) {
                    continue;
                }

                $isImage = str_starts_with(strtolower((string) ($attachment['contentType'] ?? '')), 'image/')
                    || preg_match('/\.(png|jpe?g|gif|webp|bmp)$/i', (string) ($attachment['name'] ?? '')) === 1;

                if (! $isImage && ! ($attachment['isInline'] ?? false)) {
                    continue;
                }

                $unusedUrls[] = route('admin.email.attachment', [
                    'folder' => $folder,
                    'messageId' => $encodedMessageId,
                    'attachmentId' => self::encodeGraphId($attachment['id']),
                ]);
            }

            if ($unusedUrls !== []) {
                $index = 0;
                $html = preg_replace_callback(
                    '/\bcid:([^"\'>\s]+)/i',
                    function (array $matches) use (&$index, $unusedUrls): string {
                        if (! isset($unusedUrls[$index])) {
                            return $matches[0];
                        }

                        return $unusedUrls[$index++];
                    },
                    $html
                ) ?? $html;
            }
        }

        return self::sanitizeHtmlForDisplay($html);
    }

    /**
     * Allow safe markup for email bodies. Unlike strip_tags(), this keeps
     * attributes such as img[src] and a[href] so inline images can render.
     */
    public static function sanitizeHtmlForDisplay(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        $allowed = [
            'p' => ['style', 'align'],
            'br' => [],
            'b' => [],
            'strong' => [],
            'i' => [],
            'em' => [],
            'u' => [],
            'ul' => [],
            'ol' => [],
            'li' => ['style'],
            'a' => ['href', 'title', 'target', 'rel'],
            'span' => ['style'],
            'div' => ['style', 'align'],
            'blockquote' => ['style'],
            'pre' => [],
            'code' => [],
            'img' => ['src', 'alt', 'title', 'width', 'height', 'style'],
            'table' => ['style', 'border', 'cellpadding', 'cellspacing', 'width', 'align', 'bgcolor'],
            'thead' => [],
            'tbody' => [],
            'tr' => ['style', 'align', 'bgcolor', 'valign'],
            'td' => ['style', 'align', 'bgcolor', 'width', 'height', 'colspan', 'rowspan', 'valign'],
            'th' => ['style', 'align', 'bgcolor', 'width', 'height', 'colspan', 'rowspan', 'valign'],
            'hr' => ['style'],
            'h1' => ['style'],
            'h2' => ['style'],
            'h3' => ['style'],
            'h4' => ['style'],
            'font' => ['color', 'face', 'size', 'style'],
        ];

        $wrapped = '<!DOCTYPE html><html><body>'.$html.'</body></html>';
        $previous = libxml_use_internal_errors(true);
        $document = new \DOMDocument('1.0', 'UTF-8');
        $loaded = $document->loadHTML('<?xml encoding="UTF-8">'.$wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            return htmlspecialchars(self::htmlToText($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        $body = $document->getElementsByTagName('body')->item(0);
        if (! $body) {
            return '';
        }

        self::sanitizeDomNode($body, $allowed);

        $output = '';
        foreach ($body->childNodes as $child) {
            $output .= $document->saveHTML($child);
        }

        return $output;
    }

    /**
     * @param  array<string, list<string>>  $allowed
     */
    protected static function sanitizeDomNode(\DOMNode $node, array $allowed): void
    {
        if (! $node->hasChildNodes()) {
            return;
        }

        $children = [];
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            if ($child->nodeType === XML_TEXT_NODE || $child->nodeType === XML_CDATA_SECTION_NODE) {
                continue;
            }

            if ($child->nodeType !== XML_ELEMENT_NODE) {
                $node->removeChild($child);

                continue;
            }

            /** @var \DOMElement $child */
            $tag = strtolower($child->tagName);

            if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'link', 'meta', 'form', 'input', 'button'], true)) {
                $node->removeChild($child);

                continue;
            }

            if (! array_key_exists($tag, $allowed)) {
                // Keep children of disallowed wrappers (e.g. <center>, <o:p>).
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);

                continue;
            }

            $allowedAttrs = $allowed[$tag];
            $attrs = [];
            if ($child->hasAttributes()) {
                foreach ($child->attributes as $attribute) {
                    $attrs[] = $attribute->name;
                }
            }

            foreach ($attrs as $attrName) {
                $lower = strtolower($attrName);
                if (str_starts_with($lower, 'on') || ! in_array($lower, $allowedAttrs, true)) {
                    $child->removeAttribute($attrName);

                    continue;
                }

                $value = trim((string) $child->getAttribute($attrName));
                if (in_array($lower, ['href', 'src'], true) && self::isUnsafeUrl($value)) {
                    $child->removeAttribute($attrName);
                }
            }

            if ($tag === 'a') {
                $child->setAttribute('rel', 'noopener noreferrer');
                if ($child->hasAttribute('target')) {
                    $child->setAttribute('target', '_blank');
                }
            }

            self::sanitizeDomNode($child, $allowed);
        }
    }

    protected static function isUnsafeUrl(string $url): bool
    {
        if ($url === '' || str_starts_with($url, '#')) {
            return false;
        }

        if (str_starts_with(strtolower($url), 'cid:')) {
            return false;
        }

        if (preg_match('#^(https?:|mailto:|/admin/)#i', $url) === 1) {
            return false;
        }

        return true;
    }

    public static function encodeGraphId(string $id): string
    {
        return rtrim(strtr(base64_encode($id), '+/', '-_'), '=');
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
