<?php

namespace App\Livewire\Admin\Email;

use App\Models\CredentialingCase;
use App\Models\EmailAttachment;
use App\Models\EmailMessage;
use App\Models\NotificationTemplate;
use App\Services\CredentialingEmailService;
use App\Services\MailSettingsService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::admin', ['title' => 'Email Center'])]
class EmailDashboardPage extends Component
{
    use WithPagination;

    public string $filter = 'all';

    public string $search = '';

    public bool $showLinkModal = false;

    public ?int $linkEmailId = null;

    public string $linkCaseId = '';

    public bool $showComposeModal = false;

    public string $composeCaseId = '';

    public string $composeTemplateId = '';

    public string $composeTo = '';

    public string $composeSubject = '';

    public string $composeBody = '';

    public string $composeInReplyTo = '';

    public string $composeThreadId = '';

    public bool $showThreadDrawer = false;

    public ?int $threadEmailId = null;

    public bool $syncing = false;

    public function mount(): void
    {
        $this->resetComposeForm();
    }

    public function updated($propertyName): void
    {
        if (in_array($propertyName, ['filter', 'search'])) {
            $this->resetPage();
        }
    }

    public function setFilter(string $filter): void
    {
        $this->filter = $filter;
        $this->resetPage();
    }

    public function openLinkModal(int $emailId): void
    {
        $this->linkEmailId = $emailId;
        $this->linkCaseId = '';
        $this->showLinkModal = true;
        $this->resetValidation();
    }

    public function closeLinkModal(): void
    {
        $this->showLinkModal = false;
        $this->linkEmailId = null;
        $this->linkCaseId = '';
        $this->resetValidation();
    }

    public function linkEmail(CredentialingEmailService $emailService): void
    {
        $this->normalizeOptionalIds();

        $this->validate(['linkCaseId' => 'required|exists:credentialing_cases,id']);

        $message = EmailMessage::findOrFail($this->linkEmailId);
        $case = CredentialingCase::findOrFail($this->linkCaseId);
        $emailService->linkToCase($message, $case, Auth::guard('admin')->id());

        $this->closeLinkModal();
        flash()->success('Email linked to case ' . $case->case_number);
    }

    public function openComposeModal(): void
    {
        if (! Auth::guard('admin')->user()?->can('admin.emails.send')) {
            flash()->error('You do not have permission to send emails.');

            return;
        }

        $this->showThreadDrawer = false;
        $this->showLinkModal = false;
        $this->resetComposeForm();
        $this->sanitizeComposeFields();
        $this->showComposeModal = true;
    }

    public function openReplyModal(int $emailId): void
    {
        $email = EmailMessage::findOrFail($emailId);
        $this->resetComposeForm();

        $subject = $email->subject;
        if (! str_starts_with(strtolower($subject), 're:')) {
            $subject = 'Re: ' . $subject;
        }

        $this->composeTo = $email->from_address;
        $this->composeSubject = $subject;
        $this->composeCaseId = $email->credentialing_case_id ? (string) $email->credentialing_case_id : '';
        $this->composeInReplyTo = $email->message_id ?? $email->external_message_id ?? '';
        $this->composeThreadId = $email->thread_id ?? '';
        $this->composeBody = "\n\n---\nOn " . ($email->received_at ?? $email->created_at)?->format('m/d/Y g:i A') . ", {$email->from_address} wrote:\n" . Str::limit($email->body, 500);

        $this->showComposeModal = true;
        $this->showThreadDrawer = false;
    }

    public function closeComposeModal(): void
    {
        $this->showComposeModal = false;
        $this->resetComposeForm();
        $this->resetValidation();
    }

    protected function resetComposeForm(): void
    {
        $this->composeCaseId = '';
        $this->composeTemplateId = '';
        $this->composeTo = '';
        $this->composeSubject = '';
        $this->composeBody = '';
        $this->composeInReplyTo = '';
        $this->composeThreadId = '';
    }

    public function openThread(int $emailId): void
    {
        $this->threadEmailId = $emailId;
        $this->showThreadDrawer = true;
    }

    public function closeThread(): void
    {
        $this->showThreadDrawer = false;
        $this->threadEmailId = null;
    }

    protected function sanitizeComposeFields(): void
    {
        foreach (['composeTo', 'composeSubject', 'composeBody', 'composeInReplyTo', 'composeThreadId'] as $field) {
            if ($this->{$field} === 'undefined' || $this->{$field} === 'null') {
                $this->{$field} = '';
            }
        }
    }

    protected function normalizeOptionalIds(): void
    {
        foreach (['linkCaseId', 'composeCaseId', 'composeTemplateId'] as $property) {
            if ($this->{$property} === null) {
                $this->{$property} = '';
            }
        }
    }

    public function updatedComposeTemplateId($value): void
    {
        if (! $value) {
            return;
        }

        $template = NotificationTemplate::find($value);
        if (! $template) {
            return;
        }

        $case = $this->composeCaseId ? CredentialingCase::with(['provider.user', 'payer', 'practice'])->find($this->composeCaseId) : null;

        if ($case) {
            $rendered = $template->render([
                'case_number' => $case->case_number,
                'provider_name' => $case->provider->user->name ?? '',
                'payer_name' => $case->payer->name ?? '',
                'practice_name' => $case->practice->legal_name ?? '',
                'state' => $case->state ?? '',
                'next_follow_up_date' => $case->next_follow_up_date?->format('m/d/Y') ?? '',
            ]);
            $this->composeSubject = $rendered['subject'];
            $this->composeBody = $rendered['body'];
            $this->composeTo = $case->provider->user->email ?? $this->composeTo;
        } else {
            $this->composeSubject = $template->subject;
            $this->composeBody = $template->body;
        }
    }

    public function updatedComposeCaseId($value): void
    {
        if ($value && $this->composeTemplateId) {
            $this->updatedComposeTemplateId($this->composeTemplateId);
        } elseif ($value) {
            $case = CredentialingCase::with('provider.user')->find($value);
            $this->composeTo = $case?->provider?->user?->email ?? '';
        }
    }

    public function sendEmail(CredentialingEmailService $emailService): void
    {
        if (! Auth::guard('admin')->user()?->can('admin.emails.send')) {
            flash()->error('You do not have permission to send emails.');

            return;
        }

        $this->sanitizeComposeFields();
        $this->normalizeOptionalIds();

        $this->validate([
            'composeTo' => 'required|email',
            'composeSubject' => 'required|string|max:255',
            'composeBody' => 'required|string|max:10000',
            'composeCaseId' => 'nullable|exists:credentialing_cases,id',
            'composeTemplateId' => 'nullable|exists:notification_templates,id',
        ]);

        $case = $this->composeCaseId ? CredentialingCase::find($this->composeCaseId) : null;
        $template = $this->composeTemplateId ? NotificationTemplate::find($this->composeTemplateId) : null;
        $inReplyTo = filled($this->composeInReplyTo) ? $this->composeInReplyTo : null;
        $threadId = filled($this->composeThreadId) ? $this->composeThreadId : null;

        if ($template && $case) {
            $message = $emailService->sendFromTemplate(
                $case,
                $template,
                $this->composeTo,
                Auth::guard('admin')->id(),
                null,
                [],
                $inReplyTo,
                $threadId,
            );
        } else {
            $message = $emailService->send(
                $case,
                $this->composeTo,
                $this->composeSubject,
                $this->composeBody,
                Auth::guard('admin')->id(),
                $template,
                null,
                $inReplyTo,
                $threadId,
            );
        }

        if ($message->status === 'failed') {
            flash()->error('Email failed: ' . ($message->error_message ?? 'Unknown error'));

            return;
        }

        $this->closeComposeModal();
        $this->filter = 'sent';
        $this->resetPage();
        flash()->success('Email sent successfully.');
    }

    public function importAttachment(int $attachmentId, CredentialingEmailService $emailService): void
    {
        $attachment = EmailAttachment::with('emailMessage')->findOrFail($attachmentId);
        $message = $attachment->emailMessage;

        if (! $message->credentialing_case_id) {
            flash()->error('Link the email to a case before importing attachments.');
            return;
        }

        $case = CredentialingCase::findOrFail($message->credentialing_case_id);
        $emailService->saveAttachmentToDocument($attachment, $case, Auth::guard('admin')->id());
        flash()->success('Attachment saved to document repository.');
    }

    public function syncMailbox(): void
    {
        if ($this->syncing) {
            return;
        }

        $this->syncing = true;

        dispatch(function () {
            try {
                app(CredentialingEmailService::class)->syncInbox();
            } catch (\Throwable $e) {
                report($e);
            }
        })->afterResponse();

        flash()->success('Mailbox sync started. Refresh in a moment to see imported messages.');
        $this->syncing = false;
    }

    public function render(CredentialingEmailService $emailService, MailSettingsService $mailSettings)
    {
        $query = EmailMessage::with(['credentialingCase.provider.user', 'notificationTemplate', 'sentByAdmin', 'attachments'])
            ->latest();

        $queueFilters = [
            'all', 'inbox', 'sent', 'unlinked', 'provider_responses', 'payer_responses',
            'attachments_pending', 'replies_awaited', 'escalation', 'failed',
        ];

        if (in_array($this->filter, $queueFilters, true) && $this->filter !== 'all') {
            $query->forQueue($this->filter);
        }

        if ($this->search) {
            $term = '%' . $this->search . '%';
            $query->where(function ($q) use ($term) {
                $q->where('subject', 'like', $term)
                    ->orWhere('to_address', 'like', $term)
                    ->orWhere('from_address', 'like', $term)
                    ->orWhereHas('credentialingCase', fn ($q) => $q->where('case_number', 'like', $term));
            });
        }

        $threadMessages = collect();
        if ($this->showThreadDrawer && $this->threadEmailId) {
            $anchor = EmailMessage::find($this->threadEmailId);
            if ($anchor?->thread_id) {
                $threadMessages = EmailMessage::with('attachments')
                    ->where('thread_id', $anchor->thread_id)
                    ->orderByRaw('COALESCE(sent_at, received_at, created_at) asc')
                    ->get();
            } else {
                $threadMessages = collect([$anchor])->filter();
            }
        }

        $settings = $mailSettings->getSettings();

        return view('livewire.admin.email.email-dashboard-page', [
            'emails' => $query->paginate(15),
            'stats' => $emailService->stats(),
            'templates' => NotificationTemplate::where('is_active', true)->orderBy('name')->get(),
            'cases' => CredentialingCase::with('provider.user')->latest()->limit(100)->get(['id', 'case_number', 'provider_id']),
            'smtpConfigured' => $mailSettings->isConfigured(),
            'imapConfigured' => $mailSettings->isImapConfigured(),
            'imapLastSyncAt' => $settings['imap_last_sync_at'],
            'canSend' => Auth::guard('admin')->user()?->can('admin.emails.send') ?? false,
            'threadMessages' => $threadMessages,
        ]);
    }
}
