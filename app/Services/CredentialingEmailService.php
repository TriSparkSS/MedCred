<?php

namespace App\Services;

use App\Mail\CredentialingTemplateMail;
use App\Events\ProviderResponseReceived;
use App\Models\AuditLog;
use App\Models\CaseActivity;
use App\Models\CredentialingCase;
use App\Models\Document;
use App\Models\EmailAttachment;
use App\Models\EmailMessage;
use App\Models\NotificationTemplate;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CredentialingEmailService
{
    public function sendFromTemplate(
        CredentialingCase $case,
        NotificationTemplate $template,
        string $toAddress,
        ?int $adminId = null,
        ?string $ccAddress = null,
        array $extraVariables = [],
        ?string $inReplyTo = null,
        ?string $threadId = null,
    ): EmailMessage {
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
    ): EmailMessage {
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

        $resolvedThreadId = $threadId ?? ($case ? 'case-' . $case->id : Str::uuid()->toString());
        $messageId = $this->generateMessageId();

        $message = EmailMessage::create([
            'credentialing_case_id' => $case?->id,
            'notification_template_id' => $template?->id,
            'sent_by_admin_id' => $adminId,
            'direction' => 'outbound',
            'thread_id' => $resolvedThreadId,
            'message_id' => $messageId,
            'in_reply_to' => $inReplyTo,
            'from_address' => $from,
            'to_address' => $toAddress,
            'cc_address' => $ccAddress,
            'subject' => $subject,
            'body' => $body,
            'status' => 'pending',
            'queue_category' => 'sent',
        ]);

        try {
            Mail::to($toAddress)->send(new CredentialingTemplateMail(
                $subject,
                $body,
                $messageId,
                $inReplyTo,
                $from,
                $fromName,
            ));

            $message->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $message->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'queue_category' => 'failed',
            ]);

            return $message->fresh();
        }

        try {
            if ($case) {
                $case->addActivity('email', 'Email sent: ' . $subject, $adminId);
            }

            AuditLog::record('email_sent', $message, $adminId, ['case_id' => $case?->id]);
        } catch (\Throwable) {
            // Post-send logging must not mark the email as failed.
        }

        return $message->fresh();
    }

    public function logInbound(array $data, ?int $adminId = null): EmailMessage
    {
        $message = EmailMessage::create([
            'credentialing_case_id' => $data['credentialing_case_id'] ?? null,
            'provider_id' => $data['provider_id'] ?? null,
            'direction' => 'inbound',
            'thread_id' => $data['thread_id'] ?? null,
            'external_message_id' => $data['external_message_id'] ?? $data['message_id'] ?? null,
            'message_id' => $data['message_id'] ?? null,
            'in_reply_to' => $data['in_reply_to'] ?? null,
            'references' => $data['references'] ?? null,
            'imap_uid' => $data['imap_uid'] ?? null,
            'from_address' => $data['from_address'],
            'to_address' => $data['to_address'] ?? config('credentialing.mailbox.from_address'),
            'subject' => $data['subject'],
            'body' => $data['body'],
            'status' => 'received',
            'received_at' => $data['received_at'] ?? now(),
            'has_pending_attachments' => $data['has_pending_attachments'] ?? false,
            'is_unlinked' => empty($data['credentialing_case_id']),
        ]);

        if ($message->credentialing_case_id) {
            $message->credentialingCase?->addActivity(
                'email',
                'Inbound email received: ' . $message->subject,
                $adminId
            );
        }

        $category = app(EmailMatchingService::class)->categorizeQueue($message);
        $message->update(['queue_category' => $category]);

        if ($category === 'provider_responses' && $message->credentialingCase) {
            ProviderResponseReceived::dispatch($message->fresh(), $message->credentialingCase, $adminId);
        }

        return $message->fresh();
    }

    /**
     * @return array{imported: int, skipped: int, errors: array<int, string>}
     */
    public function syncInbox(): array
    {
        return app(ImapMailboxService::class)->sync();
    }

    public function linkToCase(EmailMessage $message, CredentialingCase $case, ?int $adminId = null): void
    {
        $message->update([
            'credentialing_case_id' => $case->id,
            'thread_id' => $message->thread_id ?: 'case-' . $case->id,
            'is_unlinked' => false,
        ]);

        $category = app(EmailMatchingService::class)->categorizeQueue($message->fresh());
        $message->update(['queue_category' => $category]);

        $case->addActivity('email', 'Email linked to case: ' . $message->subject, $adminId);
        AuditLog::record('email_linked', $message, $adminId, ['case_id' => $case->id]);
    }

    public function saveAttachmentToDocument(EmailAttachment $attachment, CredentialingCase $case, ?int $adminId = null): Document
    {
        $document = Document::create([
            'credentialing_case_id' => $case->id,
            'provider_id' => $case->provider_id,
            'practice_id' => $case->practice_id,
            'title' => pathinfo($attachment->original_name, PATHINFO_FILENAME),
            'uploaded_by_admin_id' => $adminId,
            'status' => 'active',
        ]);

        $newPath = 'documents/' . $document->id . '/' . $attachment->original_name;
        Storage::disk('public')->copy($attachment->file_path, $newPath);

        $document->addVersion(
            new UploadedFile(Storage::disk('public')->path($newPath), $attachment->original_name),
            $adminId,
            'Imported from email attachment'
        );

        $attachment->update(['document_id' => $document->id]);
        $case->syncChecklistFromDocument($document);
        $case->addActivity('system', 'Document imported from email: ' . $attachment->original_name, $adminId);

        return $document;
    }

    public function stats(): array
    {
        return [
            'total_sent' => EmailMessage::outbound()->where('status', 'sent')->count(),
            'inbox_count' => EmailMessage::inbound()
                ->whereIn('queue_category', ['inbox', 'provider_responses', 'payer_responses'])
                ->count(),
            'pending_replies' => EmailMessage::outbound()
                ->where('status', 'sent')
                ->whereDoesntHave('credentialingCase', fn ($q) => $q)
                ->count(),
            'unlinked_inbound' => EmailMessage::inbound()->unlinked()->count(),
            'bounced_failed' => EmailMessage::whereIn('status', ['failed', 'bounced'])->count(),
            'reminders_sent' => EmailMessage::outbound()
                ->whereHas('notificationTemplate', fn ($q) => $q->where('category', 'reminder'))
                ->count(),
        ];
    }

    protected function generateMessageId(): string
    {
        return Str::uuid() . '@' . $this->messageIdDomain();
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
