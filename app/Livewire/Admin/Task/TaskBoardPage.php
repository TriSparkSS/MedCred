<?php

namespace App\Livewire\Admin\Task;

use App\Enums\AdminRole;
use App\Enums\TaskStatus;
use App\Models\Admin;
use App\Models\CredentialingCase;
use App\Models\Payer;
use App\Models\Priority;
use App\Models\ProviderDetails;
use App\Models\Task;
use App\Services\TaskService;
use App\Services\TaskSyncService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::admin', ['title' => 'Tasks & Follow-ups'])]
class TaskBoardPage extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    #[Url(as: 'view', history: true)]
    public string $viewMode = 'board';

    public bool $showCreateModal = false;

    public bool $showEditModal = false;

    public ?int $editingTaskId = null;

    public array $formData = [];

    public string $reassignReason = '';

    #[Url(as: 'assignee', history: true)]
    public string $filterAssignee = '';

    #[Url(as: 'my_tasks', history: true)]
    public bool $filterMyTasks = false;

    #[Url(as: 'case_id', history: true)]
    public string $filterCaseId = '';

    #[Url(as: 'provider_id', history: true)]
    public string $filterProviderId = '';

    #[Url(as: 'payer_id', history: true)]
    public string $filterPayerId = '';

    #[Url(as: 'task_type', history: true)]
    public string $filterTaskType = '';

    #[Url(as: 'status', history: true)]
    public string $filterStatus = '';

    #[Url(as: 'priority', history: true)]
    public string $filterPriority = '';

    #[Url(as: 'chip', history: true)]
    public string $filterChip = '';

    #[Url(as: 'search', history: true)]
    public string $filterSearch = '';

    protected function rules(): array
    {
        return [
            'formData.title' => 'required|string|max:255',
            'formData.description' => 'nullable|string|max:2000',
            'formData.credentialing_case_id' => 'nullable|exists:credentialing_cases,id',
            'formData.provider_id' => 'nullable|exists:provider_details,id',
            'formData.payer_id' => 'nullable|exists:payers,id',
            'formData.assigned_admin_id' => 'nullable|exists:admins,id',
            'formData.priority_id' => 'nullable|exists:priorities,id',
            'formData.due_date' => 'nullable|date',
            'formData.follow_up_date' => 'nullable|date',
            'formData.task_type' => 'nullable|string|max:50',
        ];
    }

    public function mount(): void
    {
        $admin = Auth::guard('admin')->user();

        if (request()->boolean('my_tasks')) {
            $this->filterMyTasks = true;
        }

        if ($admin?->hasRole(AdminRole::CredentialingExecutive->value) && ! request()->has('my_tasks')) {
            $this->filterMyTasks = true;
        }

        if ($taskId = request()->query('task_id')) {
            $this->dispatch('openTaskDetail', taskId: (int) $taskId);
        }
    }

    #[On('taskUpdated')]
    public function refreshBoard(): void
    {
        // triggers re-render
    }

    public function setViewMode(string $mode): void
    {
        $this->viewMode = $mode;
        $this->resetPage();
    }

    public function setChip(string $chip): void
    {
        $this->filterChip = $this->filterChip === $chip ? '' : $chip;
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        $this->authorize('create', Task::class);

        $this->formData = [
            'title' => '',
            'description' => null,
            'assigned_admin_id' => Auth::guard('admin')->id(),
            'task_type' => 'manual',
            'due_date' => now()->toDateString(),
            'follow_up_date' => null,
            'credentialing_case_id' => $this->filterCaseId ?: null,
            'provider_id' => $this->filterProviderId ?: null,
            'payer_id' => $this->filterPayerId ?: null,
            'priority_id' => null,
        ];
        $this->resetValidation();
        $this->showCreateModal = true;
    }

    public function openEditModal(int $id): void
    {
        $task = Task::findOrFail($id);
        $this->authorize('update', $task);

        $this->editingTaskId = $id;
        $this->formData = [
            'title' => $task->title,
            'description' => $task->description,
            'credentialing_case_id' => $task->credentialing_case_id,
            'provider_id' => $task->provider_id,
            'payer_id' => $task->payer_id,
            'assigned_admin_id' => $task->assigned_admin_id,
            'priority_id' => $task->priority_id,
            'due_date' => $task->due_date?->format('Y-m-d'),
            'follow_up_date' => $task->follow_up_date?->format('Y-m-d'),
            'task_type' => $task->task_type ?? 'manual',
        ];
        $this->showEditModal = true;
    }

    public function openDetail(int $id): void
    {
        $this->dispatch('openTaskDetail', taskId: $id);
    }

    public function saveTask(TaskService $taskService): void
    {
        $adminId = Auth::guard('admin')->id();

        if (! $adminId) {
            abort(403);
        }

        $this->authorize('create', Task::class);
        $this->normalizeFormData();
        $this->validate();

        $taskService->create($this->formData, $adminId);

        $this->showCreateModal = false;
        $this->resetFormData();
        flash()->success('Task created.');
    }

    public function updateTask(TaskService $taskService): void
    {
        $adminId = Auth::guard('admin')->id();

        if (! $adminId) {
            abort(403);
        }

        $task = Task::findOrFail($this->editingTaskId);
        $this->authorize('update', $task);
        $this->normalizeFormData();
        $this->validate();

        $taskService->update($task, $this->formData, $adminId);

        $this->showEditModal = false;
        $this->editingTaskId = null;
        flash()->success('Task updated.');
    }

    protected function normalizeFormData(): void
    {
        foreach (['credentialing_case_id', 'provider_id', 'payer_id', 'assigned_admin_id', 'priority_id'] as $key) {
            if (array_key_exists($key, $this->formData) && $this->formData[$key] === '') {
                $this->formData[$key] = null;
            }
        }

        foreach (['due_date', 'follow_up_date', 'description'] as $key) {
            if (array_key_exists($key, $this->formData) && $this->formData[$key] === '') {
                $this->formData[$key] = null;
            }
        }
    }

    protected function resetFormData(): void
    {
        $this->formData = [];
        $this->resetValidation();
    }

    public function completeTask(int $id, TaskService $taskService): void
    {
        $task = Task::findOrFail($id);
        $this->authorize('update', $task);
        $taskService->complete($task, Auth::guard('admin')->id());
    }

    public function reopenTask(int $id, TaskService $taskService): void
    {
        $task = Task::findOrFail($id);
        $this->authorize('reopen', $task);
        $taskService->reopen($task, Auth::guard('admin')->id());
    }

    public function escalateTask(int $id, TaskService $taskService): void
    {
        $task = Task::findOrFail($id);
        $this->authorize('escalate', $task);
        $taskService->escalate($task, Auth::guard('admin')->id());
        flash()->success('Task escalated.');
    }

    public function assignTask(int $id, $adminId, TaskService $taskService): void
    {
        $task = Task::findOrFail($id);
        $this->authorize('assign', $task);
        $taskService->assign($task, $adminId ?: null, Auth::guard('admin')->id());
        flash()->success('Task assigned.');
    }

    public function reassignTask(int $id, $adminId, TaskService $taskService): void
    {
        $task = Task::findOrFail($id);
        $this->authorize('assign', $task);

        if (! $this->reassignReason) {
            flash()->error('Please provide a reason for reassignment.');
            return;
        }

        $taskService->reassign($task, $adminId ?: null, Auth::guard('admin')->id(), $this->reassignReason);
        $this->reassignReason = '';
        flash()->success('Task reassigned.');
    }

    public function deleteTask(int $id, TaskService $taskService): void
    {
        $task = Task::findOrFail($id);
        $this->authorize('delete', $task);
        $taskService->delete($task, Auth::guard('admin')->id());
        flash()->info('Task deleted.');
    }

    public function updatingFilterSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterAssignee(): void
    {
        $this->resetPage();
    }

    public function updatingFilterCaseId(): void
    {
        $this->resetPage();
    }

    public function updatingFilterProviderId(): void
    {
        $this->resetPage();
    }

    public function updatingFilterPayerId(): void
    {
        $this->resetPage();
    }

    public function updatingFilterTaskType(): void
    {
        $this->resetPage();
    }

    public function updatingFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatingFilterChip(): void
    {
        $this->resetPage();
    }

    protected function baseQuery()
    {
        $query = Task::with([
            'assignedAdmin',
            'provider.user',
            'credentialingCase.payer',
            'payer',
            'priority',
        ]);

        if ($this->filterMyTasks) {
            $query->where('assigned_admin_id', Auth::guard('admin')->id());
        }

        if ($this->filterAssignee) {
            $query->where('assigned_admin_id', $this->filterAssignee);
        }

        if ($this->filterCaseId) {
            $query->where('credentialing_case_id', $this->filterCaseId);
        }

        if ($this->filterProviderId) {
            $query->where('provider_id', $this->filterProviderId);
        }

        if ($this->filterPayerId) {
            $query->where('payer_id', $this->filterPayerId);
        }

        if ($this->filterTaskType) {
            $query->where('task_type', $this->filterTaskType);
        }

        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
        }

        if ($this->filterPriority) {
            $query->where('priority_id', $this->filterPriority);
        }

        if ($this->filterChip) {
            $query->forColumn($this->filterChip);
        }

        if ($this->filterSearch) {
            $search = '%' . $this->filterSearch . '%';
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', $search)
                    ->orWhere('description', 'like', $search)
                    ->orWhereHas('credentialingCase', fn ($q) => $q->where('case_number', 'like', $search));
            });
        }

        $admin = Auth::guard('admin')->user();
        if ($admin) {
            app(\App\Services\AdminScopeService::class)->scopeTasks($query, $admin);
        }

        return $query;
    }

    public function render(TaskSyncService $taskSync)
    {
        $columns = [
            'escalated' => ['label' => 'Escalated', 'color' => 'danger'],
            'overdue' => ['label' => 'Overdue', 'color' => 'danger'],
            'due_today' => ['label' => 'Due Today', 'color' => 'warning'],
            'upcoming' => ['label' => 'Upcoming', 'color' => 'info'],
            'completed' => ['label' => 'Completed', 'color' => 'success'],
        ];

        $board = [];
        if ($this->viewMode === 'board') {
            foreach ($columns as $key => $meta) {
                $board[$key] = [
                    'meta' => $meta,
                    'tasks' => (clone $this->baseQuery())->forColumn($key)->orderBy('due_date')->limit(50)->get(),
                ];
            }
        }

        $listTasks = $this->viewMode === 'list'
            ? $this->baseQuery()->orderByDesc('updated_at')->paginate(20)
            : null;

        $openCount = (clone $this->baseQuery())->open()->count();
        $closedCategories = ['approved', 'closed'];

        return view('livewire.admin.task.task-board-page', [
            'board' => $board,
            'listTasks' => $listTasks,
            'openCount' => $openCount,
            'admins' => Admin::assignable()->get(['id', 'name', 'username']),
            'providers' => ProviderDetails::with('user')->orderBy('id')->limit(200)->get(),
            'payers' => Payer::orderBy('name')->get(['id', 'name']),
            'cases' => CredentialingCase::whereHas('status', fn ($q) => $q->whereNotIn('dashboard_category', $closedCategories))
                ->with('payer')
                ->latest()
                ->limit(100)
                ->get(['id', 'case_number', 'payer_id']),
            'priorities' => Priority::where('is_active', true)->orderBy('sort_order')->get(),
            'statuses' => TaskStatus::cases(),
            'taskTypeLabels' => fn (string $type) => $taskSync->taskTypeLabel($type),
        ]);
    }
}
