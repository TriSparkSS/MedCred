<?php

namespace App\Services;

use App\Enums\ProviderStatus;
use App\Models\CaseActivity;
use App\Models\CredentialingCase;
use App\Models\DelayOwner;
use App\Models\Document;
use App\Models\ProviderDetails;
use App\Models\Task;
use Illuminate\Support\Collection;

class DashboardService
{
    protected array $closedCategories = ['approved', 'closed'];

    public function stats(): array
    {
        $rushCount = CredentialingCase::active()
            ->whereHas('priority', fn ($q) => $q->where('name', 'like', '%rush%'))
            ->count();

        return [
            'active_providers' => ProviderDetails::where('status', ProviderStatus::APPROVED)->count(),
            'apps_in_progress' => CredentialingCase::active()->count(),
            'pending_payer' => CredentialingCase::filterCategory('payer')->count(),
            'pending_provider' => CredentialingCase::filterCategory('provider')->count(),
            'overdue_followups' => CredentialingCase::filterCategory('overdue')->count()
                + Task::open()->forColumn('overdue')->count(),
            'expiring_documents' => Document::expiringSoon(30)->count(),
            'rush_cases' => $rushCount,
        ];
    }

    public function delayBreakdown(): array
    {
        $activeCases = CredentialingCase::active()
            ->with('delayOwner')
            ->get();

        $total = $activeCases->count();

        if ($total === 0) {
            return [
                'labels' => ['No active cases'],
                'series' => [100],
                'items' => [],
            ];
        }

        $grouped = $activeCases->groupBy(fn ($case) => $case->delay_owner_id ?? 0);

        $items = $grouped->map(function (Collection $cases, $ownerId) use ($total) {
            $owner = $ownerId
                ? DelayOwner::find($ownerId)
                : null;

            $count = $cases->count();

            return [
                'name' => $owner?->name ?? 'Unassigned',
                'count' => $count,
                'percent' => round(($count / $total) * 100),
            ];
        })->sortByDesc('count')->values();

        return [
            'labels' => $items->pluck('name')->all(),
            'series' => $items->pluck('count')->all(),
            'items' => $items->all(),
        ];
    }

    public function workQueue(int $limit = 15): Collection
    {
        $tasks = Task::open()
            ->with(['provider.user', 'credentialingCase', 'priority'])
            ->where(function ($q) {
                $q->whereDate('due_date', '<=', now())
                    ->orWhereDate('due_date', now());
            })
            ->orderBy('due_date')
            ->limit($limit)
            ->get()
            ->map(fn (Task $task) => [
                'type' => 'task',
                'id' => $task->id,
                'priority' => $task->priority?->name ?? 'Normal',
                'priority_class' => $task->kanbanColumn() === 'overdue' ? 'danger' : 'warning',
                'title' => $task->title,
                'provider' => $task->provider->user->name ?? '—',
                'status' => $task->kanbanColumn() === 'overdue' ? 'Overdue' : 'Due Today',
                'status_class' => $task->kanbanColumn() === 'overdue' ? 'danger' : 'warning',
                'url' => route('admin.tasks.kanban'),
                'due_date' => $task->due_date,
            ]);

        $cases = CredentialingCase::filterCategory('overdue')
            ->with(['provider.user', 'payer', 'status'])
            ->orderBy('next_follow_up_date')
            ->limit($limit)
            ->get()
            ->map(fn (CredentialingCase $case) => [
                'type' => 'case',
                'id' => $case->id,
                'priority' => $case->is_escalated ? 'Escalated' : 'Normal',
                'priority_class' => $case->is_escalated ? 'danger' : 'danger',
                'title' => 'Follow-up: ' . ($case->payer->name ?? 'Case'),
                'provider' => $case->provider->user->name ?? '—',
                'status' => 'Overdue',
                'status_class' => 'danger',
                'url' => route('admin.credentials', ['category' => 'overdue']),
                'due_date' => $case->next_follow_up_date,
            ]);

        return $tasks->concat($cases)
            ->sortBy('due_date')
            ->take($limit)
            ->values();
    }

    public function recentActivity(int $limit = 20): Collection
    {
        return CaseActivity::with([
            'credentialingCase.provider.user',
            'credentialingCase.payer',
            'admin',
        ])
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function notificationCounts(): array
    {
        $adminId = auth()->guard('admin')->id();
        $taskNotifications = $adminId
            ? app(\App\Services\TaskNotificationService::class)->unreadAssignmentCountForAdmin($adminId)
            : 0;

        return [
            'expiring_documents' => Document::expiringSoon(30)->count(),
            'overdue_tasks' => Task::open()->forColumn('overdue')->count(),
            'overdue_cases' => CredentialingCase::filterCategory('overdue')->count(),
            'task_assignments' => $taskNotifications,
        ];
    }

    public function notificationTotal(): int
    {
        $counts = $this->notificationCounts();

        return $counts['expiring_documents'] + $counts['overdue_tasks'] + $counts['overdue_cases'] + $counts['task_assignments'];
    }
}
