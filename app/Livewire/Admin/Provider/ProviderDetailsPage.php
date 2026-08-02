<?php

namespace App\Livewire\Admin\Provider;

use App\Enums\TaskStatus;
use App\Models\DelayOwnerHistory;
use App\Models\EmailCaseLink;
use App\Models\ProviderDetails;
use App\Models\Task;
use App\Services\BillingReadinessService;
use App\Services\TaskService;
use App\Services\TaskSyncService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

#[Layout('layouts::admin', ['title' => 'Provider Workbench'])]
class ProviderDetailsPage extends Component
{
    use AuthorizesRequests;

    public ProviderDetails $provider;

    public string $activeTab = 'overview';

    public string $taskSection = 'open';

    public bool $showTaskModal = false;

    public array $taskForm = [];

    public function mount(ProviderDetails $provider): void
    {
        $this->provider = $provider->load([
            'user', 'specialty', 'practices.primaryAddress', 'practices.locations',
            'providerPracticeLocations.practice', 'providerPracticeLocations.location',
            'credentialingCases.payer', 'credentialingCases.status', 'credentialingCases.delayOwner',
            'credentialingCases.assignedAdmin', 'credentialingCases.practice',
            'documents.documentType', 'documents.versions',
        ]);
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function setTaskSection(string $section): void
    {
        $this->taskSection = $section;
    }

    public function openCreateTaskModal(): void
    {
        $this->authorize('create', Task::class);

        $this->taskForm = [
            'title' => '',
            'description' => '',
            'provider_id' => $this->provider->id,
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
        ]);

        $taskService->create($this->taskForm, Auth::guard('admin')->id());
        $this->showTaskModal = false;
        flash()->success('Task created.');
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
        $cases = $this->provider->credentialingCases()->with(['payer', 'status', 'delayOwner', 'assignedAdmin'])->get();

        $timelineActivities = $cases->flatMap(fn ($case) => $case->activities()->with('admin')->get())
            ->sortByDesc('created_at')
            ->take(100);

        $caseIds = $cases->pluck('id')->all();
        $emails = EmailCaseLink::query()
            ->with('credentialingCase:id,case_number')
            ->whereIn('credentialing_case_id', $caseIds)
            ->latest()
            ->limit(50)
            ->get();

        $providerTasks = $this->providerTasksQuery()->orderBy('due_date')->get();
        $openTaskCount = Task::where('provider_id', $this->provider->id)->open()->count();

        $delayHistories = DelayOwnerHistory::whereIn('credentialing_case_id', $cases->pluck('id'))
            ->with(['delayOwner', 'changedByAdmin'])
            ->latest()
            ->limit(50)
            ->get();

        $billingCases = $cases->filter(fn ($c) => $c->status?->dashboard_category === 'approved');

        return view('livewire.admin.provider.provider-details-page', [
            'cases' => $cases,
            'timelineActivities' => $timelineActivities,
            'emails' => $emails,
            'providerTasks' => $providerTasks,
            'openTaskCount' => $openTaskCount,
            'delayHistories' => $delayHistories,
            'billingCases' => $billingCases,
            'billingService' => $billing,
            'taskTypeLabel' => fn (string $type) => $taskSync->taskTypeLabel($type),
            'admins' => \App\Models\Admin::assignable()->get(['id', 'name', 'username']),
        ]);
    }
}
