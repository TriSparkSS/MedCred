<?php

namespace App\Services;

use App\Data\SentEmailResult;
use App\Mail\CredentialingTemplateMail;
use App\Models\AuditLog;
use App\Models\CredentialingCase;
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

        try {
            Mail::to($toAddress)->send(new CredentialingTemplateMail(
                $subject,
                $body,
                $messageId,
                $inReplyTo,
                $from,
                $fromName,
            ));
        } catch (\Throwable $e) {
            return new SentEmailResult(
                success: false,
                messageId: $messageId,
                errorMessage: $e->getMessage(),
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

    public function linkMessageToCase(string $messageId, CredentialingCase $case, ?int $adminId = null): void
    {
        $this->caseLinks->link($messageId, $case->id);
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
