<?php

namespace App\Livewire\Admin\Email;

use App\Models\CredentialingCase;
use App\Models\NotificationTemplate;
use App\Services\CredentialingEmailService;
use App\Services\EmailCaseLinkService;
use App\Services\GraphMailboxService;
use App\Services\MailSettingsService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts::admin', ['title' => 'Email Center'])]
class EmailDashboardPage extends Component
{
    #[Url(as: 'folder', history: true)]
    public string $filter = 'inbox';

    #[Url(as: 'q', history: true)]
    public string $search = '';

    #[Url(as: 'page', history: true)]
    public int $page = 1;

    public bool $showLinkModal = false;

    public string $linkMessageId = '';

    public string $linkCaseId = '';

    public bool $showComposeModal = false;

    public string $composeCaseId = '';

    public string $composeTemplateId = '';

    public string $composeTo = '';

    public string $composeSubject = '';

    public string $composeBody = '';

    public string $composeInReplyTo = '';

    public string $composeThreadId = '';

    public bool $refreshing = false;

    public ?array $notification = null;

    public ?string $mailboxError = null;

    public function mount(): void
    {
        $this->resetComposeForm();
        if (! in_array($this->filter, ['inbox', 'sent'], true)) {
            $this->filter = 'inbox';
        }
    }

    public function dismissNotification(): void
    {
        $this->notification = null;
    }

    protected function notify(string $type, string $message): void
    {
        $this->notification = [
            'type' => match ($type) {
                'success' => 'success',
                'error', 'danger' => 'danger',
                'warning' => 'warning',
                default => 'info',
            },
            'message' => $message,
        ];

        flash()->{$type === 'danger' ? 'error' : $type}($message);
    }

    public function updated($propertyName): void
    {
        if (in_array($propertyName, ['filter', 'search'], true)) {
            $this->page = 1;
        }
    }

    public function setFilter(string $filter): void
    {
        $this->filter = in_array($filter, ['inbox', 'sent'], true) ? $filter : 'inbox';
        $this->page = 1;
    }

    public function gotoPage(int $page): void
    {
        $this->page = max(1, $page);
    }

    public function previousPage(): void
    {
        $this->page = max(1, $this->page - 1);
    }

    public function nextPage(): void
    {
        $this->page++;
    }

    public function refreshMailbox(GraphMailboxService $graph): void
    {
        if (! $graph->isConfigured()) {
            $this->notify('error', 'Microsoft Graph is not configured. Set GRAPH_* env vars.');

            return;
        }

        $this->refreshing = true;
        try {
            $graph->clearCache();
            $this->notify('success', 'Mailbox refreshed from Microsoft Graph.');
        } catch (\Throwable $e) {
            $this->notify('error', $e->getMessage());
        } finally {
            $this->refreshing = false;
        }
    }

    public function openLinkModal(string $messageId): void
    {
        $this->showComposeModal = false;
        $this->linkMessageId = $messageId;
        $this->linkCaseId = '';
        $this->showLinkModal = true;
        $this->resetValidation();
    }

    public function closeLinkModal(): void
    {
        $this->showLinkModal = false;
        $this->linkMessageId = '';
        $this->linkCaseId = '';
    }

    public function linkEmail(CredentialingEmailService $emailService): void
    {
        $this->validate([
            'linkMessageId' => 'required|string',
            'linkCaseId' => 'required|exists:credentialing_cases,id',
        ]);

        $case = CredentialingCase::findOrFail((int) $this->linkCaseId);
        $admin = Auth::guard('admin')->user();
        if ($admin && ! app(\App\Services\AdminScopeService::class)->canAccessCase($admin, $case)) {
            abort(403, 'You do not have access to this case.');
        }
        $emailService->linkMessageToCase($this->linkMessageId, $case, Auth::guard('admin')->id());
        $this->closeLinkModal();
        $this->notify('success', 'Email linked to case '.$case->case_number);
    }

    public function openComposeModal(): void
    {
        $this->showLinkModal = false;
        $this->resetComposeForm();
        $this->showComposeModal = true;
    }

    public function openReplyModal(string $to, string $subject, string $inReplyTo = '', string $caseId = ''): void
    {
        $this->showLinkModal = false;
        $this->composeTo = $to;
        $this->composeSubject = str_starts_with(strtolower($subject), 're:') ? $subject : 'Re: '.$subject;
        $this->composeBody = '';
        $this->composeInReplyTo = $inReplyTo;
        $this->composeThreadId = '';
        $this->composeCaseId = $caseId;
        $this->composeTemplateId = '';
        $this->showComposeModal = true;
    }

    public function closeComposeModal(): void
    {
        $this->showComposeModal = false;
        $this->resetComposeForm();
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

    public function updatedComposeTemplateId($value): void
    {
        if (! $value) {
            return;
        }

        $template = NotificationTemplate::find($value);
        if (! $template) {
            return;
        }

        $case = $this->composeCaseId
            ? CredentialingCase::with(['provider.user', 'payer', 'practice'])->find($this->composeCaseId)
            : null;

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
            $this->notify('error', 'You do not have permission to send emails.');

            return;
        }

        $this->validate([
            'composeTo' => 'required|email',
            'composeSubject' => 'required|string|max:255',
            'composeBody' => 'required|string|max:10000',
            'composeCaseId' => 'nullable|exists:credentialing_cases,id',
            'composeTemplateId' => 'nullable|exists:notification_templates,id',
        ]);

        try {
            $case = $this->composeCaseId ? CredentialingCase::find($this->composeCaseId) : null;
            $template = $this->composeTemplateId ? NotificationTemplate::find($this->composeTemplateId) : null;
            $inReplyTo = filled($this->composeInReplyTo) ? $this->composeInReplyTo : null;

            if ($template && $case) {
                $result = $emailService->sendFromTemplate(
                    $case,
                    $template,
                    $this->composeTo,
                    Auth::guard('admin')->id(),
                    null,
                    [],
                    $inReplyTo,
                );
            } else {
                $result = $emailService->send(
                    $case,
                    $this->composeTo,
                    $this->composeSubject,
                    $this->composeBody,
                    Auth::guard('admin')->id(),
                    $template,
                    null,
                    $inReplyTo,
                );
            }

            if ($result->failed()) {
                $this->notify('error', 'Email failed: '.($result->errorMessage ?? 'Unknown error'));

                return;
            }

            $this->closeComposeModal();
            $this->filter = 'sent';
            $this->page = 1;
            $this->notify('success', 'Email sent successfully.');
            app(GraphMailboxService::class)->clearCache();
        } catch (\Throwable $e) {
            $this->notify('error', 'Email failed: '.$e->getMessage());
        }
    }

    public function render(
        CredentialingEmailService $emailService,
        MailSettingsService $mailSettings,
        GraphMailboxService $graph,
        EmailCaseLinkService $caseLinks,
    ) {
        $folder = $graph->normalizeFolder($this->filter);
        $this->mailboxError = null;
        $emails = collect();
        $total = 0;
        $perPage = 15;
        $stats = ['inbox_count' => 0, 'total_sent' => 0, 'graph_configured' => $graph->isConfigured()];
        $caseMap = [];

        if ($graph->isConfigured()) {
            try {
                $page = $graph->listFolder($folder, $this->page, $perPage, $this->search ?: null);
                $emails = $page->items;
                $total = $page->total;
                $lastPage = $page->lastPage();
                if ($this->page > $lastPage) {
                    $this->page = $lastPage;
                    $page = $graph->listFolder($folder, $this->page, $perPage, $this->search ?: null);
                    $emails = $page->items;
                    $total = $page->total;
                }
                $stats = $emailService->stats($graph);
                $caseMap = $caseLinks->caseIdsForMessageIds(
                    $emails->map(fn ($m) => $m->internetMessageId)->all()
                );
            } catch (\Throwable $e) {
                $this->mailboxError = $e->getMessage();
            }
        }

        $linkedCases = [];
        if ($caseMap !== []) {
            $linkedCases = CredentialingCase::query()
                ->whereIn('id', array_values($caseMap))
                ->get(['id', 'case_number'])
                ->keyBy('id');
        }

        return view('livewire.admin.email.email-dashboard-page', [
            'emails' => $emails,
            'total' => $total,
            'perPage' => $perPage,
            'lastPage' => max(1, (int) ceil($total / $perPage)),
            'stats' => $stats,
            'caseMap' => $caseMap,
            'linkedCases' => $linkedCases,
            'templates' => NotificationTemplate::where('is_active', true)->orderBy('name')->get(),
            'cases' => tap(CredentialingCase::with('provider.user')->latest(), function ($q) {
                $admin = Auth::guard('admin')->user();
                if ($admin) {
                    app(\App\Services\AdminScopeService::class)->scopeCredentialingCases($q, $admin);
                }
            })->limit(100)->get(['id', 'case_number', 'provider_id']),
            'smtpConfigured' => $mailSettings->isConfigured(),
            'graphConfigured' => $graph->isConfigured(),
            'graphMailbox' => config('services.microsoft_graph.mailbox'),
            'canSend' => Auth::guard('admin')->user()?->can('admin.emails.send') ?? false,
            'canManageMail' => Auth::guard('admin')->user()?->can('admin.settings.mail') ?? false,
            'canLink' => Auth::guard('admin')->user()?->can('admin.emails.link') ?? false,
        ]);
    }
}
