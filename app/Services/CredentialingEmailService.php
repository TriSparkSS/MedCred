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
        array $extraVariables = []
    ): EmailMessage {
        $variables = array_merge($this->caseVariables($case), $extraVariables);
        $rendered = $template->render($variables);

        return $this->send($case, $toAddress, $rendered['subject'], $rendered['body'], $adminId, $template, $ccAddress);
    }

    public function send(
        ?CredentialingCase $case,
        string $toAddress,
        string $subject,
        string $body,
        ?int $adminId = null,
        ?NotificationTemplate $template = null,
        ?string $ccAddress = null
    ): EmailMessage {
        $from = config('credentialing.mailbox.from_address');
        $threadId = $case ? 'case-' . $case->id : Str::uuid()->toString();

        $message = EmailMessage::create([
            'credentialing_case_id' => $case?->id,
            'notification_template_id' => $template?->id,
            'sent_by_admin_id' => $adminId,
            'direction' => 'outbound',
            'thread_id' => $threadId,
            'from_address' => $from,
            'to_address' => $toAddress,
            'cc_address' => $ccAddress,
            'subject' => $subject,
            'body' => $body,
            'status' => 'pending',
        ]);

        try {
            Mail::to($toAddress)->send(new CredentialingTemplateMail($subject, $body, $from));

            $message->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            if ($case) {
                $case->addActivity('email', 'Email sent: ' . $subject, $adminId);
            }

            AuditLog::record('email_sent', $message, $adminId, ['case_id' => $case?->id]);
        } catch (\Throwable $e) {
            $message->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }

        return $message->fresh();
    }

    public function logInbound(array $data, ?int $adminId = null): EmailMessage
    {
        $message = EmailMessage::create([
            'credentialing_case_id' => $data['credentialing_case_id'] ?? null,
            'direction' => 'inbound',
            'thread_id' => $data['thread_id'] ?? null,
            'external_message_id' => $data['external_message_id'] ?? null,
            'from_address' => $data['from_address'],
            'to_address' => $data['to_address'] ?? config('credentialing.mailbox.from_address'),
            'subject' => $data['subject'],
            'body' => $data['body'],
            'status' => 'received',
            'received_at' => $data['received_at'] ?? now(),
        ]);

        if ($message->credentialing_case_id) {
            $message->credentialingCase?->addActivity(
                'email',
                'Inbound email received: ' . $message->subject,
                $adminId
            );

            $category = app(EmailMatchingService::class)->categorizeQueue($message);
            $message->update(['queue_category' => $category]);

            if ($category === 'provider_responses' && $message->credentialingCase) {
                ProviderResponseReceived::dispatch($message->fresh(), $message->credentialingCase, $adminId);
            }
        }

        return $message;
    }

    public function linkToCase(EmailMessage $message, CredentialingCase $case, ?int $adminId = null): void
    {
        $message->update([
            'credentialing_case_id' => $case->id,
            'thread_id' => $message->thread_id ?: 'case-' . $case->id,
        ]);

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
