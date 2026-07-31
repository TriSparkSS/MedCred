<?php

namespace App\Livewire\Admin\Email;

use App\Models\CredentialingCase;
use App\Models\EmailAttachment;
use App\Models\EmailMessage;
use App\Models\NotificationTemplate;
use App\Services\CredentialingEmailService;
use App\Services\MailSettingsService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::admin', ['title' => 'Email'])]
class EmailViewPage extends Component
{
    public EmailMessage $email;

    public bool $showLinkModal = false;

    public string $linkCaseId = '';

    public bool $showComposeModal = false;

    public string $composeCaseId = '';

    public string $composeTemplateId = '';

    public string $composeTo = '';

    public string $composeSubject = '';

    public string $composeBody = '';

    public string $composeInReplyTo = '';

    public string $composeThreadId = '';

    public function mount(EmailMessage $email): void
    {
        $this->email = $email->load(['credentialingCase', 'attachments', 'notificationTemplate', 'sentByAdmin']);
    }

    public function openLinkModal(): void
    {
        $this->linkCaseId = (string) ($this->email->credentialing_case_id ?? '');
        $this->showLinkModal = true;
    }

    public function closeLinkModal(): void
    {
        $this->showLinkModal = false;
        $this->linkCaseId = '';
    }

    public function linkToCase(CredentialingEmailService $emailService): void
    {
        $this->validate(['linkCaseId' => 'required|exists:credentialing_cases,id']);
        $case = CredentialingCase::findOrFail((int) $this->linkCaseId);
        $emailService->linkToCase($this->email, $case, Auth::guard('admin')->id());
        $this->email->refresh();
        $this->closeLinkModal();
        flash()->success('Email linked to case '.$case->case_number);
    }

    public function openReplyModal(): void
    {
        $this->composeCaseId = (string) ($this->email->credentialing_case_id ?? '');
        $this->composeTo = $this->email->direction === 'inbound'
            ? (string) $this->email->from_address
            : (string) $this->email->to_address;
        $this->composeSubject = str_starts_with(strtolower((string) $this->email->subject), 're:')
            ? (string) $this->email->subject
            : 'Re: '.$this->email->subject;
        $this->composeBody = '';
        $this->composeInReplyTo = (string) ($this->email->message_id ?? '');
        $this->composeThreadId = (string) ($this->email->thread_id ?? '');
        $this->composeTemplateId = '';
        $this->showComposeModal = true;
    }

    public function closeComposeModal(): void
    {
        $this->showComposeModal = false;
    }

    public function sendEmail(CredentialingEmailService $emailService, MailSettingsService $mailSettings): void
    {
        $this->validate([
            'composeTo' => 'required|email',
            'composeSubject' => 'required|string|max:500',
            'composeBody' => 'required|string',
            'composeCaseId' => 'nullable|exists:credentialing_cases,id',
        ]);

        $mailSettings->assertConfigured();

        $case = $this->composeCaseId
            ? CredentialingCase::find((int) $this->composeCaseId)
            : null;

        $template = $this->composeTemplateId
            ? NotificationTemplate::find((int) $this->composeTemplateId)
            : null;

        if ($template && $case) {
            $emailService->sendFromTemplate(
                $case,
                $template,
                $this->composeTo,
                Auth::guard('admin')->id(),
                null,
                [],
                $this->composeInReplyTo ?: null,
                $this->composeThreadId ?: null,
            );
        } else {
            $emailService->send(
                $case,
                $this->composeTo,
                $this->composeSubject,
                $this->composeBody,
                Auth::guard('admin')->id(),
                $template,
                null,
                $this->composeInReplyTo ?: null,
                $this->composeThreadId ?: null,
            );
        }

        flash()->success('Reply sent.');
        $this->closeComposeModal();
    }

    public function saveAttachment(int $attachmentId, CredentialingEmailService $emailService): void
    {
        $attachment = EmailAttachment::where('email_message_id', $this->email->id)->findOrFail($attachmentId);

        if (! $this->email->credentialing_case_id) {
            flash()->error('Link the email to a case before importing attachments.');

            return;
        }

        $case = CredentialingCase::findOrFail($this->email->credentialing_case_id);
        $emailService->saveAttachmentToDocument($attachment, $case, Auth::guard('admin')->id());
        flash()->success('Attachment saved to document repository.');
    }

    public function render(MailSettingsService $mailSettings)
    {
        $threadId = $this->email->thread_id;
        $threadMessages = $threadId
            ? EmailMessage::with(['attachments', 'credentialingCase', 'sentByAdmin'])
                ->where('thread_id', $threadId)
                ->orderByRaw('COALESCE(sent_at, received_at, created_at) asc')
                ->get()
            : collect([$this->email->load('attachments')]);

        return view('livewire.admin.email.email-view-page', [
            'threadMessages' => $threadMessages,
            'cases' => CredentialingCase::with('provider.user')->latest()->limit(100)->get(['id', 'case_number', 'provider_id']),
            'templates' => NotificationTemplate::where('is_active', true)->orderBy('name')->get(),
            'smtpConfigured' => $mailSettings->isConfigured(),
            'canSend' => Auth::guard('admin')->user()?->can('admin.emails.send') ?? false,
            'canManageMail' => Auth::guard('admin')->user()?->can('admin.settings.manage') ?? false,
            'canLink' => Auth::guard('admin')->user()?->can('admin.emails.link') ?? false,
        ]);
    }
}
