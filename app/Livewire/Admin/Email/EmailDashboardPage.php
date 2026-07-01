<?php

namespace App\Livewire\Admin\Email;

use App\Models\CredentialingCase;
use App\Models\EmailAttachment;
use App\Models\EmailMessage;
use App\Models\NotificationTemplate;
use App\Services\CredentialingEmailService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::admin', ['title' => 'Email Center'])]
class EmailDashboardPage extends Component
{
    use WithPagination;

    public $filter = 'all';

    public $search = '';

    public $showLinkModal = false;

    public $linkEmailId = null;

    public $linkCaseId = '';

    public $showComposeModal = false;

    public $composeCaseId = '';

    public $composeTemplateId = '';

    public $composeTo = '';

    public $composeSubject = '';

    public $composeBody = '';

    public function updated($propertyName): void
    {
        if (in_array($propertyName, ['filter', 'search'])) {
            $this->resetPage();
        }
    }

    public function openLinkModal(int $emailId): void
    {
        $this->linkEmailId = $emailId;
        $this->linkCaseId = '';
        $this->showLinkModal = true;
    }

    public function linkEmail(CredentialingEmailService $emailService): void
    {
        $this->validate(['linkCaseId' => 'required|exists:credentialing_cases,id']);

        $message = EmailMessage::findOrFail($this->linkEmailId);
        $case = CredentialingCase::findOrFail($this->linkCaseId);
        $emailService->linkToCase($message, $case, Auth::guard('admin')->id());

        $this->showLinkModal = false;
        flash()->success('Email linked to case ' . $case->case_number);
    }

    public function openComposeModal(): void
    {
        $this->composeCaseId = '';
        $this->composeTemplateId = '';
        $this->composeTo = '';
        $this->composeSubject = '';
        $this->composeBody = '';
        $this->showComposeModal = true;
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
            $this->composeTo = $case->provider->user->email ?? '';
        }
    }

    public function sendEmail(CredentialingEmailService $emailService): void
    {
        $this->validate([
            'composeTo' => 'required|email',
            'composeSubject' => 'required|string|max:255',
            'composeBody' => 'required|string|max:10000',
            'composeCaseId' => 'nullable|exists:credentialing_cases,id',
        ]);

        $case = $this->composeCaseId ? CredentialingCase::find($this->composeCaseId) : null;
        $template = $this->composeTemplateId ? NotificationTemplate::find($this->composeTemplateId) : null;

        if ($template && $case) {
            $emailService->sendFromTemplate($case, $template, $this->composeTo, Auth::guard('admin')->id());
        } else {
            $emailService->send($case, $this->composeTo, $this->composeSubject, $this->composeBody, Auth::guard('admin')->id(), $template);
        }

        $this->showComposeModal = false;
        flash()->success('Email queued for delivery.');
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
        \App\Jobs\SyncCredentialingMailboxJob::dispatch();
        flash()->success('Mailbox sync queued.');
    }

    public function render(CredentialingEmailService $emailService)
    {
        $query = EmailMessage::with(['credentialingCase.provider.user', 'notificationTemplate', 'sentByAdmin', 'attachments'])
            ->latest();

        $queueFilters = [
            'all', 'inbox', 'sent', 'unlinked', 'provider_responses', 'payer_responses',
            'attachments_pending', 'replies_awaited', 'escalation', 'failed',
        ];

        if (in_array($this->filter, $queueFilters, true) && $this->filter !== 'all') {
            if ($this->filter === 'sent') {
                $query->outbound();
            } else {
                $query->forQueue($this->filter);
            }
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

        return view('livewire.admin.email.email-dashboard-page', [
            'emails' => $query->paginate(15),
            'stats' => $emailService->stats(),
            'templates' => NotificationTemplate::where('is_active', true)->orderBy('name')->get(),
            'cases' => CredentialingCase::with('provider.user')->latest()->limit(100)->get(['id', 'case_number', 'provider_id']),
        ]);
    }
}
