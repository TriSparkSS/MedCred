<?php

namespace App\Services;

use App\Enums\TaskType;
use App\Models\CredentialingCase;
use App\Models\Practice;
use App\Models\ProviderDetails;
use App\Models\Task;
use Illuminate\Support\Collection;

class PortalActionItemsService
{
    public function __construct(
        protected ProviderDashboardService $providerDashboard,
        protected PracticeDashboardService $practiceDashboard,
    ) {}

    public function providerActionCount(ProviderDetails $provider): int
    {
        return $this->providerDashboard->stats($provider)['pending_action']
            + $this->providerDashboard->outstandingDocuments($provider)->count()
            + $this->providerTasks($provider)->count();
    }

    public function practiceActionCount(Practice $practice): int
    {
        return $this->practiceDashboard->stats($practice)['pending_action']
            + $this->practiceDashboard->outstandingDocuments($practice)->count()
            + $this->practiceTasks($practice)->count();
    }

    public function providerTasks(ProviderDetails $provider): Collection
    {
        return Task::query()
            ->where('provider_id', $provider->id)
            ->open()
            ->whereIn('task_type', TaskType::providerPortalTypes())
            ->with(['credentialingCase.payer', 'priority'])
            ->orderBy('due_date')
            ->get();
    }

    public function practiceTasks(Practice $practice): Collection
    {
        $providerIds = $practice->providers()->pluck('id');

        if ($providerIds->isEmpty()) {
            return collect();
        }

        return Task::query()
            ->whereIn('provider_id', $providerIds)
            ->open()
            ->whereIn('task_type', TaskType::providerPortalTypes())
            ->with(['credentialingCase.payer', 'priority', 'provider.user'])
            ->orderBy('due_date')
            ->get();
    }

    public function providerItems(ProviderDetails $provider): array
    {
        $outstanding = $this->providerDashboard->outstandingDocuments($provider);
        $pendingCases = CredentialingCase::where('provider_id', $provider->id)
            ->with(['payer', 'status'])
            ->filterCategory('provider')
            ->latest()
            ->get();

        $overdueCases = CredentialingCase::where('provider_id', $provider->id)
            ->with(['payer', 'status'])
            ->whereNotNull('next_follow_up_date')
            ->whereDate('next_follow_up_date', '<', now())
            ->whereHas('status', fn ($q) => $q->whereNotIn('dashboard_category', ['approved', 'closed']))
            ->get();

        $providerTasks = $this->providerTasks($provider);

        return [
            'outstanding_documents' => $outstanding,
            'pending_cases' => $pendingCases,
            'overdue_followups' => $overdueCases,
            'provider_tasks' => $providerTasks,
            'grouped_tasks' => $this->groupTasksForKanban($providerTasks),
            'total_count' => $this->providerActionCount($provider),
        ];
    }

    public function practiceItems(Practice $practice): array
    {
        $outstanding = $this->practiceDashboard->outstandingDocuments($practice);
        $pendingCases = CredentialingCase::where('practice_id', $practice->id)
            ->with(['payer', 'status', 'provider.user'])
            ->filterCategory('provider')
            ->latest()
            ->get();

        $overdueCases = CredentialingCase::where('practice_id', $practice->id)
            ->with(['payer', 'status', 'provider.user'])
            ->whereNotNull('next_follow_up_date')
            ->whereDate('next_follow_up_date', '<', now())
            ->whereHas('status', fn ($q) => $q->whereNotIn('dashboard_category', ['approved', 'closed']))
            ->get();

        $practiceTasks = $this->practiceTasks($practice);

        return [
            'outstanding_documents' => $outstanding,
            'pending_cases' => $pendingCases,
            'overdue_followups' => $overdueCases,
            'provider_tasks' => $practiceTasks,
            'grouped_tasks' => $this->groupTasksForKanban($practiceTasks),
            'total_count' => $this->practiceActionCount($practice),
        ];
    }

    public function groupTasksForKanban(Collection $tasks): array
    {
        $columns = [
            'overdue' => ['label' => 'Overdue', 'color' => 'danger', 'tasks' => collect()],
            'due_today' => ['label' => 'Due Today', 'color' => 'warning', 'tasks' => collect()],
            'upcoming' => ['label' => 'Upcoming', 'color' => 'info', 'tasks' => collect()],
        ];

        foreach ($tasks as $task) {
            $column = $task->kanbanColumn();

            if ($column === 'escalated') {
                $column = 'overdue';
            }

            if (! isset($columns[$column])) {
                $column = 'upcoming';
            }

            $columns[$column]['tasks']->push($task);
        }

        return $columns;
    }
}
