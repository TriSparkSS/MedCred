<?php

namespace App\Livewire\Admin\Task;

use App\Models\Admin;
use App\Models\CredentialingCase;
use App\Models\Priority;
use App\Models\ProviderDetails;
use App\Models\Task;
use App\Services\TaskSyncService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::admin', ['title' => 'Tasks & Follow-ups'])]
class TaskkanbanPage extends Component
{
    public $showModal = false;

    public $formData = [];

    public $filterAssignee = '';

    public $filterMyTasks = false;

    public $filterCaseId = '';

    public $filterProviderId = '';

    public $filterTaskType = '';

    public $filterCaseSearch = '';

    protected function rules(): array
    {
        return [
            'formData.title' => 'required|string|max:255',
            'formData.description' => 'nullable|string|max:2000',
            'formData.credentialing_case_id' => 'nullable|exists:credentialing_cases,id',
            'formData.provider_id' => 'nullable|exists:provider_details,id',
            'formData.assigned_admin_id' => 'nullable|exists:admins,id',
            'formData.priority_id' => 'nullable|exists:priorities,id',
            'formData.due_date' => 'nullable|date',
            'formData.task_type' => 'nullable|string|max:50',
        ];
    }

    public function mount(): void
    {
        if (request()->boolean('my_tasks')) {
            $this->filterMyTasks = true;
        }

        if ($caseId = request()->query('case_id')) {
            $this->filterCaseId = (string) $caseId;
        }

        if ($providerId = request()->query('provider_id')) {
            $this->filterProviderId = (string) $providerId;
        }

        if ($taskType = request()->query('task_type')) {
            $this->filterTaskType = $taskType;
        }
    }

    public function openCreateModal(): void
    {
        $this->formData = [
            'assigned_admin_id' => Auth::guard('admin')->id(),
            'task_type' => 'manual',
            'due_date' => now()->toDateString(),
            'credentialing_case_id' => $this->filterCaseId ?: null,
            'provider_id' => $this->filterProviderId ?: null,
        ];
        $this->showModal = true;
    }

    public function saveTask(): void
    {
        $this->validate();

        Task::create([
            ...$this->formData,
            'created_by_admin_id' => Auth::guard('admin')->id(),
        ]);

        $this->showModal = false;
        flash()->success('Task created.');
    }

    public function completeTask(int $id): void
    {
        Task::findOrFail($id)->markComplete();
    }

    public function reopenTask(int $id): void
    {
        Task::findOrFail($id)->markIncomplete();
    }

    public function reassignTask(int $id, $adminId): void
    {
        Task::findOrFail($id)->update(['assigned_admin_id' => $adminId ?: null]);
        flash()->success('Task reassigned.');
    }

    public function deleteTask(int $id): void
    {
        Task::findOrFail($id)->delete();
        flash()->info('Task deleted.');
    }

    protected function baseQuery()
    {
        $query = Task::with([
            'assignedAdmin',
            'provider.user',
            'credentialingCase.payer',
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

        if ($this->filterTaskType) {
            $query->where('task_type', $this->filterTaskType);
        }

        if ($this->filterCaseSearch) {
            $search = '%' . $this->filterCaseSearch . '%';
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', $search)
                    ->orWhereHas('credentialingCase', fn ($q) => $q->where('case_number', 'like', $search));
            });
        }

        return $query;
    }

    public function render(TaskSyncService $taskSync)
    {
        $columns = [
            'overdue' => ['label' => 'Overdue', 'color' => 'danger'],
            'due_today' => ['label' => 'Due Today', 'color' => 'warning'],
            'upcoming' => ['label' => 'Upcoming', 'color' => 'info'],
            'completed' => ['label' => 'Completed', 'color' => 'success'],
        ];

        $board = [];
        foreach ($columns as $key => $meta) {
            $board[$key] = [
                'meta' => $meta,
                'tasks' => (clone $this->baseQuery())->forColumn($key)->orderBy('due_date')->get(),
            ];
        }

        $openCount = (clone $this->baseQuery())->open()->count();

        $closedCategories = ['approved', 'closed'];

        return view('livewire.admin.task.taskkanban-page', [
            'board' => $board,
            'openCount' => $openCount,
            'admins' => Admin::orderBy('name')->get(['id', 'name']),
            'providers' => ProviderDetails::with('user')->get(),
            'cases' => CredentialingCase::whereHas('status', fn ($q) => $q->whereNotIn('dashboard_category', $closedCategories))
                ->with('payer')
                ->latest()
                ->limit(100)
                ->get(['id', 'case_number', 'payer_id']),
            'priorities' => Priority::where('is_active', true)->orderBy('sort_order')->get(),
            'taskTypeLabels' => fn (string $type) => $taskSync->taskTypeLabel($type),
        ]);
    }
}
