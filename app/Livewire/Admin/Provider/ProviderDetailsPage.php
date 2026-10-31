<?php

namespace App\Livewire\Admin\Provider;

use App\Enums\TaskStatus;
use App\Models\Admin;
use App\Models\CaseActivity;
use App\Models\CredentialingCase;
use App\Models\DelayOwnerHistory;
use App\Models\EmailCaseLink;
use App\Models\EmailMessage;
use App\Models\ProviderDetails;
use App\Models\Task;
use App\Services\AdminScopeService;
use App\Services\BillingReadinessService;
use App\Services\TaskService;
use App\Services\TaskSyncService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts::admin', ['title' => 'Provider Workbench'])]
class ProviderDetailsPage extends Component
{
    use AuthorizesRequests;

    public ProviderDetails $provider;

    #[Url(as: 'tab', history: true)]
    public string $activeTab = 'overview';

    public string $taskSection = 'open';

    public bool $showTaskModal = false;

    public array $taskForm = [];

    public string $commCaseId = '';

    public string $commType = 'note';

    public string $commSummary = '';

    public string $commContactPerson = '';

    public string $commPhoneOrFax = '';

    public string $commReferenceNumber = '';

    public string $commOutcome = '';

    public string $commNextFollowUpDate = '';

    /** @var list<string> */
    protected array $allowedTabs = [
        'overview',
        'practice_ids',
        'licenses',
        'documents',
        'payer_applications',
        'credentialing',
        'communication',
        'tasks',
        'delay',
        'timeline',
        'billing',
    ];

    public function mount(ProviderDetails $provider, AdminScopeService $scope): void
    {
        $admin = Auth::guard('admin')->user();
        if ($admin && ! $scope->canAccessProvider($admin, $provider)) {
            abort(403, 'You do not have access to this provider.');
        }

        $this->provider = $provider;
        $this->loadScopedProviderRelations();

        if (! in_array($this->activeTab, $this->allowedTabs, true)) {
            $this->activeTab = 'overview';
        }

        if ($this->commCaseId === '' && $this->provider->credentialingCases->isNotEmpty()) {
            $this->commCaseId = (string) $this->provider->credentialingCases->first()->id;
        }
    }

    protected function loadScopedProviderRelations(): void
    {
        $admin = Auth::guard('admin')->user();
        $scope = app(AdminScopeService::class);
        $assignedPracticeIds = $admin ? $scope->assignedPracticeIds($admin) : null;

        $this->provider->load([
            'user',
            'specialty',
            'credentials',
            'practices' => function ($q) use ($admin, $scope) {
                if ($admin) {
                    $scope->scopePractices($q, $admin);
                }
                $q->with('locations');
            },
            'providerPracticeLocations' => function ($q) use ($assignedPracticeIds) {
                if ($assignedPracticeIds !== null) {
                    $q->whereIn('practice_id', $assignedPracticeIds ?: [0]);
                }
                $q->with(['practice', 'location']);
            },
            'credentialingCases' => function ($q) use ($admin, $scope) {
                if ($admin) {
                    $scope->scopeCredentialingCases($q, $admin);
                }
                $q->with([
                    'practice',
                    'provider.user',
                    'payer',
                    'status',
                    'caseType',
                    'priority',
                    'delayOwner',
                    'assignedAdmin',
                    'activities.user',
                    'activities.admin',
                    'activities.credentialingCase',
                ]);
            },
            'documents' => function ($q) use ($admin, $scope) {
                if ($admin) {
                    $scope->scopeDocuments($q, $admin);
                }
                $q->with(['documentType', 'versions']);
            },
            'tasks' => function ($q) use ($admin, $scope) {
                if ($admin) {
                    $scope->scopeTasks($q, $admin);
                }
            },
        ]);
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = in_array($tab, $this->allowedTabs, true) ? $tab : 'overview';
    }

    public function setTaskSection(string $section): void
    {
        $this->taskSection = $section;
    }

    public function openCreateTaskModal(?int $caseId = null): void
    {
        $this->authorize('create', Task::class);

        if ($caseId !== null) {
            $admin = Auth::guard('admin')->user();
            $case = CredentialingCase::where('provider_id', $this->provider->id)->findOrFail($caseId);
            if ($admin) {
                abort_unless(app(AdminScopeService::class)->canAccessCase($admin, $case), 403);
            }
        }

        $this->taskForm = [
            'title' => '',
            'description' => '',
            'provider_id' => $this->provider->id,
            'credentialing_case_id' => $caseId,
            'assigned_admin_id' => Auth::guard('admin')->id(),
            'due_date' => now()->addDays(3)->toDateString(),
            'task_type' => 'manual',
        ];
        $this->showTaskModal = true;
    }

    public function saveTask(TaskService $taskService): void
    {
        $this->authorize('create', Task::class);

        $this->validate([
            'taskForm.title' => 'required|string|max:255',
            'taskForm.due_date' => 'nullable|date',
            'taskForm.assigned_admin_id' => 'nullable|exists:admins,id',
            'taskForm.credentialing_case_id' => 'nullable|exists:credentialing_cases,id',
        ]);

        $admin = Auth::guard('admin')->user();
        $payload = $this->taskForm;
        $payload['provider_id'] = $this->provider->id;
        if (empty($payload['credentialing_case_id'])) {
            $payload['credentialing_case_id'] = null;
        } else {
            $case = CredentialingCase::where('provider_id', $this->provider->id)->findOrFail((int) $payload['credentialing_case_id']);
            if ($admin) {
                abort_unless(app(AdminScopeService::class)->canAccessCase($admin, $case), 403);
            }
            $payload['credentialing_case_id'] = $case->id;
            if (empty($payload['payer_id'])) {
                $payload['payer_id'] = $case->payer_id;
            }
        }

        $taskService->create($payload, Auth::guard('admin')->id());
        $this->showTaskModal = false;
        flash()->success('Task created.');
    }

    public function addCommunicationLog(): void
    {
        $admin = Auth::guard('admin')->user();
        abort_unless($admin?->can('admin.credentials.edit'), 403);

        $this->validate([
            'commCaseId' => 'required|exists:credentialing_cases,id',
            'commType' => 'required|in:note,call_log,fax_log,portal_check,follow_up',
            'commSummary' => 'required|string|max:2000',
            'commContactPerson' => 'nullable|string|max:255',
            'commPhoneOrFax' => 'nullable|string|max:100',
            'commReferenceNumber' => 'nullable|string|max:150',
            'commOutcome' => 'nullable|string|max:255',
            'commNextFollowUpDate' => 'nullable|date',
        ]);

        $case = CredentialingCase::where('provider_id', $this->provider->id)->findOrFail((int) $this->commCaseId);
        abort_unless(app(AdminScopeService::class)->canAccessCase($admin, $case), 403);

        $metadata = array_filter([
            'contact_person' => trim($this->commContactPerson),
            'phone_or_fax' => trim($this->commPhoneOrFax),
            'reference_number' => trim($this->commReferenceNumber),
            'outcome' => trim($this->commOutcome),
            'next_follow_up_date' => trim($this->commNextFollowUpDate),
        ], fn ($value) => $value !== '');

        CaseActivity::create([
            'credentialing_case_id' => $case->id,
            'activity_type' => $this->commType,
            'admin_id' => Auth::guard('admin')->id(),
            'summary' => $this->commSummary,
            'reference_number' => $this->commReferenceNumber !== '' ? $this->commReferenceNumber : null,
            'next_action_date' => $this->commNextFollowUpDate !== '' ? $this->commNextFollowUpDate : null,
            'metadata' => $metadata !== [] ? $metadata : null,
        ]);

        $updates = ['last_action_at' => now()];
        if ($this->commNextFollowUpDate !== '') {
            $updates['next_follow_up_date'] = $this->commNextFollowUpDate;
        }
        $case->update($updates);

        $this->commSummary = '';
        $this->commContactPerson = '';
        $this->commPhoneOrFax = '';
        $this->commReferenceNumber = '';
        $this->commOutcome = '';
        $this->commNextFollowUpDate = '';

        $this->loadScopedProviderRelations();
        flash()->success('Communication entry logged.');
    }

    public function completeProviderTask(int $taskId, TaskService $taskService): void
    {
        $task = Task::where('provider_id', $this->provider->id)->findOrFail($taskId);
        $this->authorize('update', $task);
        $taskService->complete($task, Auth::guard('admin')->id());
    }

    public function openTaskDetail(int $taskId): void
    {
        $this->dispatch('openTaskDetail', taskId: $taskId);
    }

    #[On('taskUpdated')]
    public function refreshTasks(): void
    {
        // triggers re-render
    }

    protected function providerTasksQuery()
    {
        $query = Task::where('provider_id', $this->provider->id)
            ->with(['assignedAdmin', 'credentialingCase', 'priority']);

        $admin = Auth::guard('admin')->user();
        if ($admin) {
            app(AdminScopeService::class)->scopeTasks($query, $admin);
        }

        return match ($this->taskSection) {
            'completed' => $query->where(function ($q) {
                $q->whereNotNull('completed_at')->orWhere('status', TaskStatus::Completed->value);
            }),
            'overdue' => $query->overdue(),
            'due_today' => $query->dueToday(),
            'upcoming' => $query->upcoming(),
            'escalated' => $query->escalated(),
            default => $query->open(),
        };
    }

    public function render(BillingReadinessService $billing, TaskSyncService $taskSync)
    {
        $this->loadScopedProviderRelations();

        $cases = $this->provider->credentialingCases;

        $timelineActivities = $cases
            ->flatMap(fn ($case) => $case->activities)
            ->sortByDesc('created_at')
            ->take(100);

        $commActivityTypes = ['note', 'call_log', 'fax_log', 'portal_check', 'follow_up', 'email', 'email_sent', 'email_received', 'reminder_sent', 'escalation'];
        $communicationActivities = $cases
            ->flatMap(fn ($case) => $case->activities)
            ->whereIn('activity_type', $commActivityTypes)
            ->sortByDesc('id')
            ->values()
            ->take(50);

        $caseIds = $cases->pluck('id')->all();
        $emails = EmailCaseLink::query()
            ->with('credentialingCase:id,case_number')
            ->whereIn('credentialing_case_id', $caseIds)
            ->latest()
            ->limit(50)
            ->get();

        $emailMessages = EmailMessage::query()
            ->with('credentialingCase:id,case_number')
            ->where(function ($q) use ($caseIds) {
                $q->where(fn ($sq) => $sq->where('provider_id', $this->provider->id)->whereNull('credentialing_case_id'));
                if ($caseIds !== []) {
                    $q->orWhereIn('credentialing_case_id', $caseIds);
                }
            })
            ->latest()
            ->limit(50)
            ->get();

        $providerTasks = $this->providerTasksQuery()->orderBy('due_date')->get();
        $openTaskQuery = Task::where('provider_id', $this->provider->id)->open();
        $admin = Auth::guard('admin')->user();
        if ($admin) {
            app(AdminScopeService::class)->scopeTasks($openTaskQuery, $admin);
        }
        $openTaskCount = $openTaskQuery->count();

        $delayHistories = DelayOwnerHistory::whereIn('credentialing_case_id', $caseIds)
            ->with(['credentialingCase', 'previousDelayOwner', 'delayOwner', 'changedByAdmin'])
            ->latest()
            ->limit(50)
            ->get();

        $billingCases = $cases->filter(fn ($c) => $c->status?->dashboard_category === 'approved');

        return view('livewire.admin.provider.provider-details-page', [
            'cases' => $cases,
            'timelineActivities' => $timelineActivities,
            'communicationActivities' => $communicationActivities,
            'emails' => $emails,
            'emailMessages' => $emailMessages,
            'providerTasks' => $providerTasks,
            'openTaskCount' => $openTaskCount,
            'delayHistories' => $delayHistories,
            'billingCases' => $billingCases,
            'billingService' => $billing,
            'taskTypeLabel' => fn (string $type) => $taskSync->taskTypeLabel($type),
            'admins' => Admin::assignable()->get(['id', 'name', 'username']),
        ]);
    }
}
