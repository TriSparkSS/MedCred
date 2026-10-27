<?php

namespace App\Services;

use App\Data\SentEmailResult;
use App\Mail\CredentialingTemplateMail;
use App\Models\AuditLog;
use App\Models\CredentialingCase;
use App\Models\EmailCaseLink;
use App\Models\EmailMessage;
use App\Models\NotificationTemplate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class CredentialingEmailService
{
    public function __construct(
        protected EmailCaseLinkService $caseLinks,
    ) {}

    public function sendFromTemplate(
        CredentialingCase $case,
        NotificationTemplate $template,
        string $toAddress,
        ?int $adminId = null,
        ?string $ccAddress = null,
        array $extraVariables = [],
        ?string $inReplyTo = null,
        ?string $threadId = null,
    ): SentEmailResult {
        $variables = array_merge($this->caseVariables($case), $extraVariables);
        $rendered = $template->render($variables);

        return $this->send(
            $case,
            $toAddress,
            $rendered['subject'],
            $rendered['body'],
            $adminId,
            $template,
            $ccAddress,
            $inReplyTo,
            $threadId,
        );
    }

    public function send(
        ?CredentialingCase $case,
        string $toAddress,
        string $subject,
        string $body,
        ?int $adminId = null,
        ?NotificationTemplate $template = null,
        ?string $ccAddress = null,
        ?string $inReplyTo = null,
        ?string $threadId = null,
    ): SentEmailResult {
        $mailSettings = app(MailSettingsService::class);
        $mailSettings->applyToConfig();
        $mailSettings->assertConfigured();

        $from = $mailSettings->resolveFromAddress();
        $fromName = $mailSettings->resolveFromName();

        if (blank($from)) {
            throw new \RuntimeException('From email address is required. Set it in Admin → Settings → Mail.');
        }

        if (blank($toAddress)) {
            throw new \RuntimeException('Recipient email address is required.');
        }

        $messageId = $this->generateMessageId();
        $ccAddresses = [];
        $success = true;
        $errorMessage = null;

        try {
            $pendingMail = Mail::to($toAddress);

            if (filled($ccAddress)) {
                $ccAddresses = array_values(array_filter(
                    array_map('trim', preg_split('/[,;]+/', $ccAddress) ?: []),
                    fn (string $email) => $email !== ''
                ));

                if ($ccAddresses !== []) {
                    $pendingMail->cc($ccAddresses);
                }
            }

            $pendingMail->send(new CredentialingTemplateMail(
                $subject,
                $body,
                $messageId,
                $inReplyTo,
                $from,
                $fromName,
            ));
        } catch (\Throwable $e) {
            $success = false;
            $errorMessage = $e->getMessage();
        }

        if ($case || ! $success) {
            try {
                EmailMessage::create([
                    'credentialing_case_id' => $case?->id,
                    'provider_id' => $case?->provider_id,
                    'payer_id' => $case?->payer_id,
                    'direction' => 'outbound',
                    'from_address' => $from,
                    'to_address' => $toAddress,
                    'to_addresses' => [$toAddress],
                    'cc_address' => $ccAddresses !== [] ? implode(', ', $ccAddresses) : $ccAddress,
                    'cc_addresses' => $ccAddresses,
                    'subject' => $subject,
                    'body' => $body,
                    'body_text' => strip_tags($body),
                    'body_html' => $body,
                    'message_id' => $messageId,
                    'thread_id' => $threadId,
                    'in_reply_to' => $inReplyTo,
                    'status' => $success ? 'sent' : 'failed',
                    'is_unlinked' => $case === null,
                    'sent_at' => now(),
                    'sent_by_admin_id' => $adminId,
                    'notification_template_id' => $template?->id,
                    'error_message' => $errorMessage,
                ]);
            } catch (\Throwable) {
                // DB logging must never break mail sending.
            }
        }

        if (! $success) {
            return new SentEmailResult(
                success: false,
                messageId: $messageId,
                errorMessage: $errorMessage,
                caseId: $case?->id,
            );
        }

        try {
            if ($case) {
                $this->caseLinks->link($messageId, $case->id);
                $case->addActivity('email', 'Email sent: '.$subject, $adminId);
            }

            AuditLog::record('email_sent', $case, $adminId, [
                'case_id' => $case?->id,
                'message_id' => $messageId,
                'to' => $toAddress,
                'cc' => $ccAddress,
                'subject' => $subject,
                'template_id' => $template?->id,
            ]);
        } catch (\Throwable) {
            // Post-send logging must not mark the email as failed.
        }

        return new SentEmailResult(
            success: true,
            messageId: $messageId,
            caseId: $case?->id,
        );
    }

    public function linkMessageToCase(
        string $messageId,
        CredentialingCase $case,
        ?int $adminId = null,
        array $messageMeta = [],
    ): void {
        $this->caseLinks->link($messageId, $case->id);

        try {
            $normalizedId = EmailCaseLink::normalizeMessageId($messageId) ?? $messageId;
            $existing = EmailMessage::whereIn('message_id', array_values(array_unique(array_filter([$messageId, $normalizedId]))))->first();

            if ($messageMeta === []) {
                $graph = app(GraphMailboxService::class);
                if ($graph->isConfigured()) {
                    $dto = null;
                    try {
                        $dto = $graph->getMessage($messageId, null, false);
                    } catch (\Throwable) {
                        try {
                            $page = $graph->listFolder('inbox', 1, 25);
                            $dto = $page->items->first(function ($item) use ($messageId, $normalizedId) {
                                return $item->id === $messageId
                                    || $item->internetMessageId === $messageId
                                    || $item->normalizedMessageId() === $normalizedId;
                            });
                        } catch (\Throwable) {
                            $dto = null;
                        }
                    }

                    if ($dto) {
                        $messageMeta = [
                            'external_message_id' => $dto->id,
                            'direction' => $dto->direction,
                            'from_address' => $dto->fromAddress,
                            'to_address' => $dto->toAddress,
                            'cc_address' => $dto->ccAddress,
                            'subject' => $dto->subject,
                            'body' => $dto->bodyHtml ?? $dto->bodyText ?? '',
                            'thread_id' => $dto->conversationId,
                            'in_reply_to' => $dto->inReplyTo,
                            'references' => $dto->references,
                            'received_at' => $dto->direction === 'inbound' ? ($dto->date ?? now()) : null,
                            'sent_at' => $dto->direction === 'outbound' ? ($dto->date ?? now()) : null,
                            'status' => 'received',
                        ];
                    }
                }
            }

            $toAddress = $messageMeta['to_address'] ?? null;
            if ($toAddress === null && isset($messageMeta['to_addresses'])) {
                $toAddress = is_array($messageMeta['to_addresses'])
                    ? implode(', ', $messageMeta['to_addresses'])
                    : (string) $messageMeta['to_addresses'];
            }
            $toAddress = $toAddress ?? $existing?->to_address ?? (string) config('services.microsoft_graph.mailbox', 'credentialing@revantagehbs.com');

            $ccAddress = $messageMeta['cc_address'] ?? null;
            if ($ccAddress === null && isset($messageMeta['cc_addresses'])) {
                $ccAddress = is_array($messageMeta['cc_addresses'])
                    ? implode(', ', $messageMeta['cc_addresses'])
                    : (string) $messageMeta['cc_addresses'];
            }
            $ccAddress = $ccAddress ?? $existing?->cc_address;

            $body = $messageMeta['body']
                ?? $messageMeta['body_html']
                ?? $messageMeta['body_text']
                ?? $existing?->body
                ?? '';

            $lookupId = $existing?->message_id ?? $messageId;

            EmailMessage::updateOrCreate(
                ['message_id' => $lookupId],
                [
                    'credentialing_case_id' => $case->id,
                    'provider_id' => $case->provider_id,
                    'payer_id' => $case->payer_id,
                    'is_unlinked' => false,
                    'direction' => $messageMeta['direction'] ?? $existing?->direction ?? 'inbound',
                    'from_address' => $messageMeta['from_address'] ?? $existing?->from_address ?? 'unknown@example.com',
                    'to_address' => $toAddress,
                    'cc_address' => $ccAddress,
                    'subject' => $messageMeta['subject'] ?? $existing?->subject ?? '(linked email)',
                    'body' => $body,
                    'status' => $messageMeta['status'] ?? $existing?->status ?? 'received',
                    'received_at' => $messageMeta['received_at'] ?? $existing?->received_at ?? now(),
                    'sent_at' => $messageMeta['sent_at'] ?? $existing?->sent_at,
                    'thread_id' => $messageMeta['thread_id'] ?? $existing?->thread_id,
                    'in_reply_to' => $messageMeta['in_reply_to'] ?? $existing?->in_reply_to,
                    'references' => $messageMeta['references'] ?? $existing?->references,
                    'external_message_id' => $messageMeta['external_message_id'] ?? $existing?->external_message_id,
                ]
            );
        } catch (\Throwable) {
            // Never let EmailMessage persistence break case linking.
        }

        $case->addActivity('email', 'Email linked to case (Message-ID '.$messageId.')', $adminId);
        AuditLog::record('email_linked', $case, $adminId, [
            'case_id' => $case->id,
            'message_id' => $messageId,
        ]);
    }

    /**
     * @return array{inbox_count: int, total_sent: int, graph_configured: bool}
     */
    public function stats(GraphMailboxService $graph): array
    {
        if (! $graph->isConfigured()) {
            return [
                'inbox_count' => 0,
                'total_sent' => 0,
                'graph_configured' => false,
            ];
        }

        try {
            return [
                'inbox_count' => $graph->folderTotal('inbox'),
                'total_sent' => $graph->folderTotal('sentitems'),
                'graph_configured' => true,
            ];
        } catch (\Throwable) {
            return [
                'inbox_count' => 0,
                'total_sent' => 0,
                'graph_configured' => true,
            ];
        }
    }

    protected function generateMessageId(): string
    {
        return Str::uuid().'@'.$this->messageIdDomain();
    }

    protected function messageIdDomain(): string
    {
        $mailSettings = app(MailSettingsService::class);
        $fromAddress = $mailSettings->resolveFromAddress();

        if ($domain = $this->domainFromEmail($fromAddress)) {
            return $domain;
        }

        $appHost = parse_url(config('app.url', 'http://localhost'), PHP_URL_HOST);
        if ($this->isValidMessageIdDomain($appHost)) {
            return $appHost;
        }

        $configFrom = config('credentialing.mailbox.from_address');
        if ($domain = $this->domainFromEmail($configFrom)) {
            return $domain;
        }

        return 'revantagehbs.com';
    }

    protected function domainFromEmail(?string $email): ?string
    {
        if (blank($email)) {
            return null;
        }

        $parts = explode('@', $email);

        if (count($parts) === 2 && filled($parts[1])) {
            return strtolower($parts[1]);
        }

        return null;
    }

    protected function isValidMessageIdDomain(?string $host): bool
    {
        if (blank($host)) {
            return false;
        }

        $host = strtolower($host);

        return ! in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    }

    protected function caseVariables(CredentialingCase $case): array
    {
        $case->loadMissing(['provider.user', 'payer', 'practice']);

        return [
            'case_number' => $case->case_number,
            'provider_name' => $case->provider->user->name ?? '',
            'payer_name' => $case->payer->name ?? '',
            'practice_name' => $case->practice->legal_name ?? '',
            'state' => $case->state ?? '',
            'next_follow_up_date' => $case->next_follow_up_date?->format('m/d/Y') ?? '',
        ];
    }
}
