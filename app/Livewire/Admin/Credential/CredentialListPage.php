<?php

namespace App\Livewire\Admin\Credential;

use App\Events\DocumentRequestSent;
use App\Models\CredentialingCase;
use App\Models\DelayOwner;
use App\Models\NotificationTemplate;
use App\Models\Status;
use App\Models\Task;
use App\Services\BillingReadinessService;
use App\Services\CredentialingCaseService;
use App\Services\CredentialingEmailService;
use App\Services\DelayOwnershipService;
use App\Services\TaskService;
use App\Services\TaskSyncService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::admin', ['title' => 'Credentialing Tracker'])]
class CredentialListPage extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    #[Url(as: 'search', history: true)]
    public string $caseSearch = '';

    #[Url(as: 'category', history: true)]
    public string $filterCategory = '';

    #[Url(as: 'payer', history: true)]
    public string $filterPayerId = '';

    #[Url(as: 'practice', history: true)]
    public string $filterPracticeId = '';

    #[Url(as: 'provider', history: true)]
    public string $filterProviderId = '';

    #[Url(as: 'status', history: true)]
    public string $filterStatusId = '';

    #[Url(as: 'owner', history: true)]
    public string $filterOwnerId = '';

    #[Url(as: 'state', history: true)]
    public string $filterState = '';

    #[Url(as: 'revalidation', history: true)]
    public string $filterRevalidation = '';

    #[Url(as: 'recent', history: true)]
    public bool $filterRecentlySubmitted = false;

    public $selectedCaseId = null;

    public $showDrawer = false;

    public string $drawerTab = 'details';

    public $newNote = '';

    public $drawerStatusId = '';

    public $drawerDelayOwnerId = '';

    public $overrideReason = '';

    public $emailTemplateId = '';

    public $newTaskTitle = '';

    public $newTaskDueDate = '';

    public $newTaskAssigneeId = '';

    public function updated($propertyName): void
    {
        if (in_array($propertyName, [
            'caseSearch', 'filterCategory', 'filterPayerId', 'filterPracticeId', 'filterProviderId',
            'filterStatusId', 'filterOwnerId', 'filterState', 'filterRevalidation', 'filterRecentlySubmitted',
        ])) {
            if ($propertyName === 'filterPracticeId') {
                $this->filterProviderId = '';
            }
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->caseSearch = '';
        $this->filterCategory = '';
        $this->filterPayerId = '';
        $this->filterPracticeId = '';
        $this->filterProviderId = '';
        $this->filterStatusId = '';
        $this->filterOwnerId = '';
        $this->filterState = '';
        $this->filterRevalidation = '';
        $this->filterRecentlySubmitted = false;
        $this->resetPage();
    }

    public function hasActiveFilters(): bool
    {
        return (bool) (trim($this->caseSearch) || $this->filterCategory || $this->filterPayerId
            || $this->filterPracticeId || $this->filterProviderId
            || $this->filterStatusId || $this->filterOwnerId || $this->filterState
            || $this->filterRevalidation || $this->filterRecentlySubmitted);
    }

    public function setFilterCategory(?string $category): void
    {
        $this->filterCategory = $this->filterCategory === $category ? '' : ($category ?? '');
        $this->resetPage();
    }

    public function openDrawer(int $caseId): void
    {
        $this->selectedCaseId = $caseId;
        $this->drawerTab = 'details';
        $case = CredentialingCase::findOrFail($caseId);
        $this->drawerStatusId = $case->status_id;
        $this->drawerDelayOwnerId = $case->delay_owner_id ?? '';
        $this->overrideReason = '';
        $this->emailTemplateId = '';
        $this->newNote = '';
        $this->newTaskTitle = '';
        $this->newTaskDueDate = now()->addDays(3)->toDateString();
        $this->newTaskAssigneeId = $case->assigned_admin_id ?? Auth::guard('admin')->id();
        $this->billingForm = [];
        $this->showDrawer = true;
    }

    public function closeDrawer(): void
    {
        $this->showDrawer = false;
        $this->selectedCaseId = null;
        $this->newNote = '';
    }

    public function setDrawerTab(string $tab): void
    {
        $this->drawerTab = $tab;
        if ($tab === 'billing') {
            $this->loadBillingForm();
        }
    }

    public function updateCaseStatus(int $caseId, $statusId): void
    {
        $statusId = (int) $statusId;
        if ($statusId <= 0) {
            return;
        }

        $case = CredentialingCase::findOrFail($caseId);
        if ($case->status_id !== $statusId) {
            app(CredentialingCaseService::class)->changeStatus($case, $statusId, Auth::guard('admin')->id());
            flash()->success('Status updated.');
        }
    }

    public function inlineUpdate(int $caseId, string $field, $value): void
    {
        $allowed = ['assigned_admin_id', 'delay_owner_id'];
        if (! in_array($field, $allowed, true)) {
            return;
        }

        $case = CredentialingCase::findOrFail($caseId);
        app(CredentialingCaseService::class)->updateInline(
            $case,
            [$field => $value !== '' && $value !== null ? (int) $value : null],
            Auth::guard('admin')->id()
        );
    }

    public function saveBillingFields(BillingReadinessService $billing): void
    {
        if (! $this->selectedCaseId) {
            return;
        }

        $this->validate([
            'billingForm.approval_date' => 'nullable|date',
            'billingForm.effective_date' => 'nullable|date',
            'billingForm.payer_provider_id' => 'nullable|string|max:100',
            'billingForm.payer_group_id' => 'nullable|string|max:100',
            'billingForm.eft_status' => 'nullable|string|max:50',
            'billingForm.era_status' => 'nullable|string|max:50',
            'billingForm.billing_notes' => 'nullable|string|max:2000',
        ]);

        $case = CredentialingCase::findOrFail($this->selectedCaseId);
        $billing->updateBillingFields($case, $this->billingForm, Auth::guard('admin')->id());
        flash()->success('Billing fields saved.');
    }

    public $billingForm = [];

    public function loadBillingForm(): void
    {
        if (! $this->selectedCaseId) {
            return;
        }

        $case = CredentialingCase::find($this->selectedCaseId);
        if ($case) {
            $this->billingForm = [
                'approval_date' => $case->approval_date?->format('Y-m-d'),
                'effective_date' => $case->effective_date?->format('Y-m-d'),
                'payer_provider_id' => $case->payer_provider_id,
                'payer_group_id' => $case->payer_group_id,
                'eft_status' => $case->eft_status,
                'era_status' => $case->era_status,
                'billing_notes' => $case->billing_notes,
                'ready_to_bill' => $case->ready_to_bill,
                'billing_notified' => $case->billing_notified,
            ];
        }
    }

    public function notifyBilling(BillingReadinessService $billing): void
    {
        if (! $this->selectedCaseId) {
            return;
        }

        try {
            $billing->notifyBilling(CredentialingCase::findOrFail($this->selectedCaseId), Auth::guard('admin')->id());
            flash()->success('Billing team notified.');
        } catch (\InvalidArgumentException $e) {
            flash()->error($e->getMessage());
        }
    }

    public function saveDrawerStatus(CredentialingCaseService $caseService): void
    {
        if (! $this->selectedCaseId || ! $this->drawerStatusId) {
            return;
        }

        $case = CredentialingCase::findOrFail($this->selectedCaseId);
        if ($case->status_id != $this->drawerStatusId) {
            $caseService->changeStatus($case, (int) $this->drawerStatusId, Auth::guard('admin')->id());
        }

        flash()->success('Case updated.');
    }

    public function addNote(): void
    {
        $this->validate(['newNote' => 'required|string|max:2000']);

        $case = CredentialingCase::findOrFail($this->selectedCaseId);
        $case->addActivity('note', $this->newNote, Auth::guard('admin')->id());
        $this->newNote = '';
        flash()->success('Note added.');
    }

    public function saveDelayOverride(DelayOwnershipService $delayService): void
    {
        $this->validate([
            'drawerDelayOwnerId' => 'required|exists:delay_owners,id',
            'overrideReason' => 'required|string|max:500',
        ]);

        $case = CredentialingCase::findOrFail($this->selectedCaseId);
        $delayService->overrideDelayOwner(
            $case,
            (int) $this->drawerDelayOwnerId,
            $this->overrideReason,
            Auth::guard('admin')->id()
        );

        $this->overrideReason = '';
        flash()->success('Delay owner updated.');
    }

    public function sendDocumentRequest(CredentialingEmailService $emailService): void
    {
        $this->validate(['emailTemplateId' => 'required|exists:notification_templates,id']);

        $case = CredentialingCase::with('provider.user')->findOrFail($this->selectedCaseId);
        $template = NotificationTemplate::findOrFail($this->emailTemplateId);
        $to = $case->provider->user->email ?? null;

        if (! $to) {
            flash()->error('Provider has no email address on file.');
            return;
        }

        $emailService->sendFromTemplate($case, $template, $to, Auth::guard('admin')->id());
        DocumentRequestSent::dispatch($case, $template, Auth::guard('admin')->id());
        flash()->success('Email sent to provider.');
    }

    public function toggleEscalation(int $caseId): void
    {
        $case = CredentialingCase::findOrFail($caseId);
        $case->update(['is_escalated' => ! $case->is_escalated]);
        $case->refresh();
        $case->addActivity(
            'system',
            $case->is_escalated ? 'Case escalated' : 'Escalation removed',
            Auth::guard('admin')->id()
        );
        flash()->success($case->is_escalated ? 'Case escalated.' : 'Escalation removed.');
    }

    public function createFollowUpTask(TaskService $taskService): void
    {
        $this->validate([
            'newTaskTitle' => 'required|string|max:255',
            'newTaskDueDate' => 'nullable|date',
            'newTaskAssigneeId' => 'nullable|exists:admins,id',
        ]);

        $case = CredentialingCase::findOrFail($this->selectedCaseId);

        $taskService->create([
            'title' => $this->newTaskTitle,
            'description' => 'Follow-up for ' . $case->case_number,
            'credentialing_case_id' => $case->id,
            'provider_id' => $case->provider_id,
            'payer_id' => $case->payer_id,
            'assigned_admin_id' => $this->newTaskAssigneeId ?: null,
            'due_date' => $this->newTaskDueDate ?: null,
            'task_type' => 'follow_up',
        ], Auth::guard('admin')->id());

        $this->newTaskTitle = '';
        $this->newTaskDueDate = now()->addDays(3)->toDateString();
        flash()->success('Follow-up task created.');
    }

    public function completeDrawerTask(int $taskId, TaskService $taskService): void
    {
        $task = Task::where('credentialing_case_id', $this->selectedCaseId)->findOrFail($taskId);
        $this->authorize('update', $task);
        $taskService->complete($task, Auth::guard('admin')->id());
    }

    public function render(TaskSyncService $taskSync, BillingReadinessService $billing)
    {
        $query = CredentialingCase::with([
            'provider.user', 'payer', 'practice', 'status', 'delayOwner', 'assignedAdmin', 'priority',
        ])->withCount(['tasks as open_tasks_count' => fn ($q) => $q->open()]);

        if (trim($this->caseSearch) !== '') {
            $search = '%' . trim($this->caseSearch) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('case_number', 'like', $search)
                    ->orWhere('state', 'like', $search)
                    ->orWhereHas('provider.user', fn ($q) => $q->where('name', 'like', $search))
                    ->orWhereHas('practice', fn ($q) => $q->where('legal_name', 'like', $search)->orWhere('dba_name', 'like', $search))
                    ->orWhereHas('payer', fn ($q) => $q->where('name', 'like', $search))
                    ->orWhereHas('provider', fn ($q) => $q->where('npi', 'like', $search));
            });
        }

        if ($this->filterPayerId) {
            $query->where('payer_id', (int) $this->filterPayerId);
        }

        if ($this->filterPracticeId) {
            $query->where('practice_id', (int) $this->filterPracticeId);
        }

        if ($this->filterProviderId) {
            $query->where('provider_id', (int) $this->filterProviderId);
        }

        if ($this->filterStatusId) {
            $query->where('status_id', (int) $this->filterStatusId);
        }

        if ($this->filterOwnerId) {
            $query->where('assigned_admin_id', (int) $this->filterOwnerId);
        }

        if ($this->filterState) {
            $query->where('state', $this->filterState);
        }

        if ($this->filterRevalidation === '30') {
            $query->whereBetween('revalidation_due_date', [now(), now()->addDays(30)]);
        } elseif ($this->filterRevalidation === '60') {
            $query->whereBetween('revalidation_due_date', [now(), now()->addDays(60)]);
        } elseif ($this->filterRevalidation === '90') {
            $query->whereBetween('revalidation_due_date', [now(), now()->addDays(90)]);
        }

        if ($this->filterRecentlySubmitted) {
            $query->where('submission_date', '>=', now()->subDays(14)->toDateString());
        }

        $query->filterCategory($this->filterCategory ?: null);

        $cases = $query->orderByDesc('last_action_at')->paginate(15);

        $closedCategories = ['approved', 'closed'];
        $stats = [
            'total_active' => CredentialingCase::whereHas('status', fn ($q) => $q->whereNotIn('dashboard_category', $closedCategories))->count(),
            'pending_provider' => CredentialingCase::filterCategory('provider')->count(),
            'pending_payer' => CredentialingCase::filterCategory('payer')->count(),
            'overdue' => CredentialingCase::filterCategory('overdue')->count(),
        ];

        $statuses = Status::where('is_active', true)->orderBy('sort_order')->get();
        $payers = \App\Models\Payer::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $practices = \App\Models\Practice::orderBy('legal_name')->get(['id', 'legal_name', 'client_code']);
        $providers = $this->filterPracticeId
            ? \App\Models\ProviderDetails::query()
                ->whereHas('practices', fn ($q) => $q->where('practices.id', (int) $this->filterPracticeId))
                ->with('user:id,name')
                ->get()
            : collect();

        $selectedCase = $this->selectedCaseId
            ? CredentialingCase::with([
                'provider.user', 'payer', 'practice', 'location', 'status', 'delayOwner',
                'assignedAdmin', 'priority', 'caseType',
                'statusHistories.status', 'statusHistories.changedByAdmin',
                'activities.admin', 'activities.user',
                'documentItems.documentType', 'documentItems.document.versions',
                'tasks' => fn ($q) => $q->with('assignedAdmin')->orderByRaw('completed_at IS NOT NULL')->orderBy('due_date'),
                'emailCaseLinks' => fn ($q) => $q->latest()->limit(10),
                'slaTimers' => fn ($q) => $q->where('status', 'active'),
            ])->find($this->selectedCaseId)
            : null;

        $delayOwners = DelayOwner::where('is_active', true)->orderBy('name')->get();
        $emailTemplates = NotificationTemplate::where('is_active', true)->orderBy('name')->get();
        $admins = \App\Models\Admin::assignable()->get(['id', 'name', 'username']);

        $priorities = \App\Models\Priority::where('is_active', true)->orderBy('sort_order')->get();

        return view('livewire.admin.credential.credential-list-page', compact(
            'cases', 'stats', 'statuses', 'payers', 'practices', 'providers', 'selectedCase', 'delayOwners', 'emailTemplates', 'admins', 'priorities'
        ))->with([
            'taskTypeLabel' => fn (string $type) => $taskSync->taskTypeLabel($type),
            'billingService' => $billing,
        ]);
    }
}
