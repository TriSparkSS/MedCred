<?php

namespace App\Livewire\Admin\Credential;

use App\Models\CredentialingCase;
use App\Models\DelayOwner;
use App\Models\NotificationTemplate;
use App\Models\Status;
use App\Models\Task;
use App\Services\CredentialingEmailService;
use App\Services\DelayOwnershipService;
use App\Services\TaskSyncService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::admin', ['title' => 'Credentialing Tracker'])]
class CredentialListPage extends Component
{
    use WithPagination;

    public $search = '';

    public $filterCategory = '';

    public $filterPayerId = '';

    public $filterStatusId = '';

    public $selectedCaseId = null;

    public $showDrawer = false;

    public $newNote = '';

    public $drawerStatusId = '';

    public $drawerDelayOwnerId = '';

    public $overrideReason = '';

    public $emailTemplateId = '';

    public $newTaskTitle = '';

    public $newTaskDueDate = '';

    public $newTaskAssigneeId = '';

    public function mount(): void
    {
        if ($category = request()->query('category')) {
            $this->filterCategory = $category;
        }

        if ($search = request()->query('search')) {
            $this->search = $search;
        }
    }

    public function updated($propertyName): void
    {
        if (in_array($propertyName, ['search', 'filterCategory', 'filterPayerId', 'filterStatusId'])) {
            $this->search = is_string($this->search) ? trim($this->search) : $this->search;
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->filterCategory = '';
        $this->filterPayerId = '';
        $this->filterStatusId = '';
        $this->resetPage();
    }

    public function hasActiveFilters(): bool
    {
        return (bool) ($this->search || $this->filterCategory || $this->filterPayerId || $this->filterStatusId);
    }

    public function setFilterCategory(?string $category): void
    {
        $this->filterCategory = $this->filterCategory === $category ? '' : ($category ?? '');
        $this->resetPage();
    }

    public function openDrawer(int $caseId): void
    {
        $this->selectedCaseId = $caseId;
        $case = CredentialingCase::findOrFail($caseId);
        $this->drawerStatusId = $case->status_id;
        $this->drawerDelayOwnerId = $case->delay_owner_id ?? '';
        $this->overrideReason = '';
        $this->emailTemplateId = '';
        $this->newNote = '';
        $this->newTaskTitle = '';
        $this->newTaskDueDate = now()->addDays(3)->toDateString();
        $this->newTaskAssigneeId = $case->assigned_admin_id ?? Auth::guard('admin')->id();
        $this->showDrawer = true;
    }

    public function closeDrawer(): void
    {
        $this->showDrawer = false;
        $this->selectedCaseId = null;
        $this->newNote = '';
    }

    public function updateCaseStatus(int $caseId, int $statusId): void
    {
        $case = CredentialingCase::findOrFail($caseId);
        if ($case->status_id !== $statusId) {
            $case->recordStatusChange($statusId, Auth::guard('admin')->id());
            flash()->success('Status updated.');
        }
    }

    public function saveDrawerStatus(): void
    {
        if (! $this->selectedCaseId || ! $this->drawerStatusId) {
            return;
        }

        $case = CredentialingCase::findOrFail($this->selectedCaseId);
        if ($case->status_id != $this->drawerStatusId) {
            $case->recordStatusChange((int) $this->drawerStatusId, Auth::guard('admin')->id());
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
        flash()->success('Email sent to provider.');
    }

    public function toggleEscalation(int $caseId): void
    {
        $case = CredentialingCase::findOrFail($caseId);
        $case->update(['is_escalated' => ! $case->is_escalated]);
        $case->addActivity(
            'system',
            $case->is_escalated ? 'Case escalated' : 'Escalation removed',
            Auth::guard('admin')->id()
        );
    }

    public function createFollowUpTask(): void
    {
        $this->validate([
            'newTaskTitle' => 'required|string|max:255',
            'newTaskDueDate' => 'nullable|date',
            'newTaskAssigneeId' => 'nullable|exists:admins,id',
        ]);

        $case = CredentialingCase::findOrFail($this->selectedCaseId);

        Task::create([
            'title' => $this->newTaskTitle,
            'description' => 'Follow-up for ' . $case->case_number,
            'credentialing_case_id' => $case->id,
            'provider_id' => $case->provider_id,
            'assigned_admin_id' => $this->newTaskAssigneeId ?: null,
            'created_by_admin_id' => Auth::guard('admin')->id(),
            'due_date' => $this->newTaskDueDate ?: null,
            'task_type' => 'follow_up',
        ]);

        $this->newTaskTitle = '';
        $this->newTaskDueDate = now()->addDays(3)->toDateString();
        flash()->success('Follow-up task created.');
    }

    public function completeDrawerTask(int $taskId): void
    {
        Task::where('credentialing_case_id', $this->selectedCaseId)
            ->findOrFail($taskId)
            ->markComplete();
    }

    public function render(TaskSyncService $taskSync)
    {
        $query = CredentialingCase::with([
            'provider.user', 'payer', 'practice', 'status', 'delayOwner', 'assignedAdmin', 'priority',
        ])->withCount(['tasks as open_tasks_count' => fn ($q) => $q->open()]);

        if ($this->search) {
            $search = '%' . trim($this->search) . '%';
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

        if ($this->filterStatusId) {
            $query->where('status_id', (int) $this->filterStatusId);
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

        $selectedCase = $this->selectedCaseId
            ? CredentialingCase::with([
                'provider.user', 'payer', 'practice', 'location', 'status', 'delayOwner',
                'assignedAdmin', 'priority', 'caseType',
                'statusHistories.status', 'statusHistories.changedByAdmin',
                'activities.admin', 'activities.user',
                'documentItems.documentType', 'documentItems.document.versions',
                'tasks' => fn ($q) => $q->with('assignedAdmin')->orderByRaw('completed_at IS NOT NULL')->orderBy('due_date'),
                'emailMessages' => fn ($q) => $q->latest()->limit(5),
                'slaTimers' => fn ($q) => $q->where('status', 'active'),
            ])->find($this->selectedCaseId)
            : null;

        $delayOwners = DelayOwner::where('is_active', true)->orderBy('name')->get();
        $emailTemplates = NotificationTemplate::where('is_active', true)->orderBy('name')->get();
        $admins = \App\Models\Admin::orderBy('name')->get(['id', 'name']);

        return view('livewire.admin.credential.credential-list-page', compact(
            'cases', 'stats', 'statuses', 'payers', 'selectedCase', 'delayOwners', 'emailTemplates', 'admins'
        ))->with('taskTypeLabel', fn (string $type) => $taskSync->taskTypeLabel($type));
    }
}
