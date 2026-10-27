<?php

namespace App\Livewire\Admin\Email;

use App\Data\MailMessageDto;
use App\Models\CredentialingCase;
use App\Models\EmailCaseLink;
use App\Models\EmailMessage;
use App\Models\NotificationTemplate;
use App\Models\ProviderDetails;
use App\Services\AdminScopeService;
use App\Services\CredentialingEmailService;
use App\Services\EmailCaseLinkService;
use App\Services\EmailMatchingService;
use App\Services\GraphMailboxService;
use App\Services\MailSettingsService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts::admin', ['title' => 'Email Center'])]
class EmailDashboardPage extends Component
{
    public const ALLOWED_FILTERS = ['inbox', 'unread', 'unlinked', 'bounced', 'sent'];

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

    protected function canSendEmail(): bool
    {
        $admin = Auth::guard('admin')->user();

        return (bool) ($admin && ($admin->can('admin.emails.send') || $admin->can('admin.email.send')));
    }

    protected function canLinkEmail(): bool
    {
        $admin = Auth::guard('admin')->user();

        return (bool) ($admin && ($admin->can('admin.emails.link') || $admin->can('admin.email.link')));
    }

    public function mount(): void
    {
        $this->resetComposeForm();
        if (! in_array($this->filter, self::ALLOWED_FILTERS, true)) {
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
        $this->filter = in_array($filter, self::ALLOWED_FILTERS, true) ? $filter : 'inbox';
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
        abort_unless($this->canLinkEmail(), 403);

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
        $admin = Auth::guard('admin')->user();
        abort_unless($this->canLinkEmail(), 403);

        $this->validate([
            'linkMessageId' => 'required|string',
            'linkCaseId' => 'required|exists:credentialing_cases,id',
        ]);

        $case = CredentialingCase::findOrFail((int) $this->linkCaseId);
        abort_unless($admin && app(AdminScopeService::class)->canAccessCase($admin, $case), 403, 'You do not have access to this case.');

        $emailService->linkMessageToCase($this->linkMessageId, $case, $admin->id);
        $this->closeLinkModal();
        $this->notify('success', 'Email linked to case '.$case->case_number);
    }

    public function quickLinkCase(CredentialingEmailService $emailService, ?string $messageId = null, ?int $caseId = null): void
    {
        if ($messageId !== null) {
            $this->linkMessageId = $messageId;
        }
        if ($caseId !== null) {
            $this->linkCaseId = (string) $caseId;
        }

        $this->linkEmail($emailService);
    }

    public function unlinkEmail(?string $messageId = null): void
    {
        $admin = Auth::guard('admin')->user();
        abort_unless($this->canLinkEmail(), 403);

        $targetMessageId = $messageId ?: $this->linkMessageId;
        if (blank($targetMessageId)) {
            return;
        }

        $caseLinks = app(EmailCaseLinkService::class);
        $existingCase = $caseLinks->findCase($targetMessageId);
        if ($existingCase) {
            abort_unless($admin && app(AdminScopeService::class)->canAccessCase($admin, $existingCase), 403);
        }

        $caseLinks->unlink($targetMessageId);
        $normalized = EmailCaseLink::normalizeMessageId($targetMessageId);
        EmailMessage::whereIn('message_id', array_values(array_unique(array_filter([$targetMessageId, $normalized]))))
            ->update(['credentialing_case_id' => null, 'is_unlinked' => true]);

        $this->notify('info', 'Email unlinked from case.');
    }

    public function openComposeModal(): void
    {
        abort_unless($this->canSendEmail(), 403);

        $this->showLinkModal = false;
        $this->resetComposeForm();
        $this->showComposeModal = true;
    }

    public function openReplyModal(string $to, string $subject, string $inReplyTo = '', string $caseId = ''): void
    {
        abort_unless($this->canSendEmail(), 403);

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

    public function applyTemplate($value = null): void
    {
        $templateId = $value ?? $this->composeTemplateId;
        if (! $templateId) {
            return;
        }

        $template = NotificationTemplate::find($templateId);
        if (! $template) {
            return;
        }

        $case = $this->composeCaseId
            ? CredentialingCase::with(['provider.user', 'payer', 'practice'])->find($this->composeCaseId)
            : null;

        $admin = Auth::guard('admin')->user();
        if ($case && $admin && ! app(AdminScopeService::class)->canAccessCase($admin, $case)) {
            $case = null;
        }

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

    public function hydrateFromCase($value = null): void
    {
        $caseId = $value ?? $this->composeCaseId;
        if (! $caseId) {
            return;
        }

        $case = CredentialingCase::with('provider.user')->find($caseId);
        $admin = Auth::guard('admin')->user();
        if (! $case || ($admin && ! app(AdminScopeService::class)->canAccessCase($admin, $case))) {
            return;
        }

        if ($this->composeTemplateId) {
            $this->applyTemplate($this->composeTemplateId);
        } else {
            $this->composeTo = $case->provider?->user?->email ?? '';
        }
    }

    public function updatedComposeTemplateId($value): void
    {
        $this->applyTemplate($value);
    }

    public function updatedComposeCaseId($value): void
    {
        $this->hydrateFromCase($value);
    }

    public function sendEmail(CredentialingEmailService $emailService): void
    {
        $admin = Auth::guard('admin')->user();
        abort_unless($this->canSendEmail(), 403);

        $this->validate([
            'composeTo' => 'required|email',
            'composeSubject' => 'required|string|max:255',
            'composeBody' => 'required|string|max:10000',
            'composeCaseId' => 'nullable|exists:credentialing_cases,id',
            'composeTemplateId' => 'nullable|exists:notification_templates,id',
        ]);

        $case = $this->composeCaseId ? CredentialingCase::findOrFail((int) $this->composeCaseId) : null;
        if ($case) {
            abort_unless($admin && app(AdminScopeService::class)->canAccessCase($admin, $case), 403);
        }

        try {
            $template = $this->composeTemplateId ? NotificationTemplate::find($this->composeTemplateId) : null;
            $inReplyTo = filled($this->composeInReplyTo) ? $this->composeInReplyTo : null;

            if ($template && $case) {
                $result = $emailService->sendFromTemplate(
                    $case,
                    $template,
                    $this->composeTo,
                    $admin->id,
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
                    $admin->id,
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

    public function sendCompose(CredentialingEmailService $emailService): void
    {
        $this->sendEmail($emailService);
    }

    protected function localMessageToDto(EmailMessage $msg): MailMessageDto
    {
        $date = $msg->received_at ?? $msg->sent_at ?? $msg->created_at;
        $rawId = $msg->message_id ?: ('local-'.$msg->id.'@revantage.local');

        return new MailMessageDto(
            id: (string) ($msg->external_message_id ?: ('local-'.$msg->id)),
            folder: $msg->direction === 'outbound' ? 'sentitems' : 'inbox',
            direction: $msg->direction ?? 'inbound',
            internetMessageId: MailMessageDto::normalizeMessageId($rawId),
            conversationId: $msg->thread_id,
            inReplyTo: $msg->in_reply_to,
            references: $msg->references,
            subject: (string) ($msg->subject ?? '(no subject)'),
            fromAddress: (string) ($msg->from_address ?? ''),
            fromName: $msg->fromDisplayName(),
            toAddress: (string) ($msg->to_address ?? ''),
            ccAddress: $msg->cc_address,
            bodyHtml: $msg->body,
            bodyText: $msg->displayBody(),
            hasAttachments: false,
            attachments: [],
            date: $date,
            isRead: $msg->status !== 'unread',
        );
    }

    public function render(
        CredentialingEmailService $emailService,
        MailSettingsService $mailSettings,
        GraphMailboxService $graph,
        EmailCaseLinkService $caseLinks,
    ) {
        $admin = Auth::guard('admin')->user();
        $scope = app(AdminScopeService::class);
        $matching = app(EmailMatchingService::class);
        $folder = $graph->normalizeFolder($this->filter === 'sent' ? 'sent' : 'inbox');
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

                if ($this->filter === 'unread') {
                    $emails = $emails
                        ->filter(fn (MailMessageDto $m) => ! $m->isRead)
                        ->sortByDesc(fn (MailMessageDto $m) => $m->date?->timestamp ?? 0)
                        ->values();
                    $total = $emails->count();
                } elseif ($this->filter === 'unlinked') {
                    $emails = $emails
                        ->filter(function (MailMessageDto $m) use ($caseMap): bool {
                            $norm = $m->normalizedMessageId();

                            return ! $norm || ! isset($caseMap[$norm]);
                        })
                        ->values();
                    $total = $emails->count();
                } elseif ($this->filter === 'bounced') {
                    $emails = $emails
                        ->filter(fn (MailMessageDto $m) => $matching->isBounceOrFailure($m->subject, $m->fromAddress, $m->displayBody()))
                        ->values();

                    $localBounced = EmailMessage::whereIn('status', ['failed', 'bounced'])
                        ->latest('id')
                        ->limit($perPage)
                        ->get();

                    foreach ($localBounced as $localMsg) {
                        $dto = $this->localMessageToDto($localMsg);
                        if (! $emails->contains(fn (MailMessageDto $m) => $m->normalizedMessageId() === $dto->normalizedMessageId())) {
                            $emails->push($dto);
                            if ($localMsg->credentialing_case_id && $dto->normalizedMessageId()) {
                                $caseMap[$dto->normalizedMessageId()] = $localMsg->credentialing_case_id;
                            }
                        }
                    }

                    $total = $emails->count();
                }
            } catch (\Throwable $e) {
                $this->mailboxError = $e->getMessage();
            }
        } else {
            $localQuery = EmailMessage::query();

            if ($this->search !== '') {
                $term = $this->search;
                $localQuery->where(function ($q) use ($term) {
                    $q->where('subject', 'like', "%{$term}%")
                        ->orWhere('from_address', 'like', "%{$term}%")
                        ->orWhere('to_address', 'like', "%{$term}%");
                });
            }

            if ($this->filter === 'sent') {
                $localItems = (clone $localQuery)
                    ->where('direction', 'outbound')
                    ->where('status', 'sent')
                    ->latest('sent_at')
                    ->latest('id')
                    ->get();
            } elseif ($this->filter === 'unread') {
                $localItems = (clone $localQuery)
                    ->where('direction', 'inbound')
                    ->where('status', 'unread')
                    ->latest('received_at')
                    ->latest('id')
                    ->get();
            } elseif ($this->filter === 'unlinked') {
                $localItems = (clone $localQuery)
                    ->where(function ($q) {
                        $q->where('is_unlinked', true)
                            ->orWhereNull('credentialing_case_id');
                    })
                    ->latest('id')
                    ->get();
            } elseif ($this->filter === 'bounced') {
                $localItems = (clone $localQuery)
                    ->latest('id')
                    ->get()
                    ->filter(fn (EmailMessage $m) => in_array($m->status, ['failed', 'bounced'], true)
                        || $matching->isBounceOrFailure((string) $m->subject, $m->from_address, (string) $m->body))
                    ->values();
            } else {
                $localItems = (clone $localQuery)
                    ->where('direction', 'inbound')
                    ->latest('received_at')
                    ->latest('id')
                    ->get();
            }

            $emails = $localItems->map(fn (EmailMessage $m) => $this->localMessageToDto($m))->values();
            $total = $emails->count();

            $caseMap = $caseLinks->caseIdsForMessageIds(
                $emails->map(fn ($m) => $m->internetMessageId)->all()
            );

            foreach ($localItems as $localMsg) {
                if ($localMsg->credentialing_case_id && ! $localMsg->is_unlinked) {
                    $norm = EmailCaseLink::normalizeMessageId($localMsg->message_id ?: ('local-'.$localMsg->id.'@revantage.local'));
                    if ($norm) {
                        $caseMap[$norm] = $localMsg->credentialing_case_id;
                    }
                }
            }
        }

        $linkedCases = [];
        if ($caseMap !== []) {
            $linkedCasesQuery = CredentialingCase::query()->whereIn('id', array_values($caseMap));
            if ($admin) {
                $scope->scopeCredentialingCases($linkedCasesQuery, $admin);
            }
            $linkedCases = $linkedCasesQuery
                ->get(['id', 'case_number'])
                ->keyBy('id');
        }

        $suggestions = [];
        $bouncedMap = [];
        foreach ($emails as $email) {
            $normId = $email->normalizedMessageId();
            $isLinked = $normId && isset($caseMap[$normId]);

            if ($matching->isBounceOrFailure($email->subject, $email->fromAddress, $email->displayBody()) || $this->filter === 'bounced') {
                $bouncedMap[$email->id] = true;
            }

            if (! $isLinked) {
                $match = $matching->match($email->subject, $email->displayBody(), $email->fromAddress);
                if (($match['confidence'] ?? 'none') !== 'none' && (! empty($match['case']) || ! empty($match['provider']) || ! empty($match['practice']))) {
                    if (! empty($match['case']) && $admin && ! $scope->canAccessCase($admin, $match['case'])) {
                        continue;
                    }
                    $suggestions[$email->id] = $match;
                    if ($normId) {
                        $suggestions[$normId] = $match;
                    }
                }
            }
        }

        $casesQuery = CredentialingCase::with('provider.user')->latest();
        $providersQuery = ProviderDetails::with('user')->latest();
        if ($admin) {
            $scope->scopeCredentialingCases($casesQuery, $admin);
            $scope->scopeProviders($providersQuery, $admin);
        }

        return view('livewire.admin.email.email-dashboard-page', [
            'emails' => $emails,
            'total' => $total,
            'perPage' => $perPage,
            'lastPage' => max(1, (int) ceil($total / $perPage)),
            'stats' => $stats,
            'caseMap' => $caseMap,
            'linkedCases' => $linkedCases,
            'suggestions' => $suggestions,
            'bouncedMap' => $bouncedMap,
            'templates' => NotificationTemplate::where('is_active', true)->orderBy('name')->get(),
            'cases' => $casesQuery->limit(100)->get(['id', 'case_number', 'provider_id']),
            'providers' => $providersQuery->limit(100)->get(['id', 'user_id', 'npi']),
            'smtpConfigured' => $mailSettings->isConfigured(),
            'graphConfigured' => $graph->isConfigured(),
            'graphMailbox' => config('services.microsoft_graph.mailbox'),
            'canSend' => $this->canSendEmail(),
            'canManageMail' => $admin?->can('admin.settings.mail') ?? false,
            'canLink' => $this->canLinkEmail(),
        ]);
    }
}

