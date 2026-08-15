<?php

namespace App\Services;

use App\Enums\ProviderStatus;
use App\Models\Admin;
use App\Models\CaseActivity;
use App\Models\CredentialingCase;
use App\Models\DelayOwner;
use App\Models\Document;
use App\Models\ProviderDetails;
use App\Models\Task;
use Illuminate\Support\Collection;

class DashboardService
{
    public function __construct(
        protected AdminScopeService $scope
    ) {}

    protected array $closedCategories = ['approved', 'closed'];

    protected function admin(): ?Admin
    {
        return auth()->guard('admin')->user();
    }

    public function stats(): array
    {
        $admin = $this->admin();

        $providerQuery = ProviderDetails::query()->where('status', ProviderStatus::APPROVED);
        if ($admin) {
            $this->scope->scopeProviders($providerQuery, $admin);
        }

        $caseQuery = fn () => tap(CredentialingCase::query(), function ($q) use ($admin) {
            if ($admin) {
                $this->scope->scopeCredentialingCases($q, $admin);
            }
        });

        $rushCount = $caseQuery()
            ->whereHas('status', fn ($q) => $q->whereNotIn('dashboard_category', $this->closedCategories))
            ->whereHas('priority', fn ($q) => $q->where('name', 'like', '%rush%'))
            ->count();

        $activeCases = $caseQuery()->whereHas('status', fn ($q) => $q->whereNotIn('dashboard_category', $this->closedCategories));

        $documentQuery = Document::expiringSoon(30);
        if ($admin && $this->scope->isPracticeScoped($admin)) {
            $ids = $this->scope->assignedPracticeIds($admin) ?? [];
            if ($ids === []) {
                $documentQuery->whereRaw('0 = 1');
            } else {
                $documentQuery->where(function ($q) use ($ids) {
                    $q->whereIn('practice_id', $ids)
                        ->orWhereHas('credentialingCase', fn ($cq) => $cq->whereIn('practice_id', $ids))
                        ->orWhereHas('provider.practices', fn ($pq) => $pq->whereIn('practices.id', $ids));
                });
            }
        }

        $taskOverdueQuery = Task::open()->forColumn('overdue');
        if ($admin && $this->scope->isPracticeScoped($admin)) {
            $ids = $this->scope->assignedPracticeIds($admin) ?? [];
            if ($ids === []) {
                $taskOverdueQuery->whereRaw('0 = 1');
            } else {
                $taskOverdueQuery->whereHas('credentialingCase', fn ($q) => $q->whereIn('practice_id', $ids));
            }
        }

        return [
            'active_providers' => $providerQuery->count(),
            'apps_in_progress' => (clone $activeCases)->count(),
            'pending_payer' => $this->scopedCategoryCount('payer', $admin),
            'pending_provider' => $this->scopedCategoryCount('provider', $admin),
            'overdue_followups' => $this->scopedCategoryCount('overdue', $admin) + $taskOverdueQuery->count(),
            'expiring_documents' => $documentQuery->count(),
            'rush_cases' => $rushCount,
        ];
    }

    protected function scopedCategoryCount(string $category, ?Admin $admin): int
    {
        $query = CredentialingCase::filterCategory($category);
        if ($admin) {
            $this->scope->scopeCredentialingCases($query, $admin);
        }

        return $query->count();
    }

    public function delayBreakdown(): array
    {
        $admin = $this->admin();
        $query = CredentialingCase::active()->with('delayOwner');
        if ($admin) {
            $this->scope->scopeCredentialingCases($query, $admin);
        }

        $activeCases = $query->get();
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
        $admin = $this->admin();

        $taskQuery = Task::open()
            ->with(['provider.user', 'credentialingCase', 'priority'])
            ->where(function ($q) {
                $q->whereDate('due_date', '<=', now())
                    ->orWhereDate('due_date', now());
            });

        if ($admin && $this->scope->isPracticeScoped($admin)) {
            $ids = $this->scope->assignedPracticeIds($admin) ?? [];
            if ($ids === []) {
                $taskQuery->whereRaw('0 = 1');
            } else {
                $taskQuery->whereHas('credentialingCase', fn ($q) => $q->whereIn('practice_id', $ids));
            }
        }

        $tasks = $taskQuery->orderBy('due_date')
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

        $caseQuery = CredentialingCase::filterCategory('overdue')
            ->with(['provider.user', 'payer', 'status'])
            ->orderBy('next_follow_up_date');

        if ($admin) {
            $this->scope->scopeCredentialingCases($caseQuery, $admin);
        }

        $cases = $caseQuery->limit($limit)
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
        $admin = $this->admin();
        $query = CaseActivity::with([
            'credentialingCase.provider.user',
            'credentialingCase.payer',
            'admin',
        ])->latest();

        if ($admin && $this->scope->isPracticeScoped($admin)) {
            $ids = $this->scope->assignedPracticeIds($admin) ?? [];
            if ($ids === []) {
                $query->whereRaw('0 = 1');
            } else {
                $query->whereHas('credentialingCase', fn ($q) => $q->whereIn('practice_id', $ids));
            }
        }

        return $query->limit($limit)->get();
    }

    public function notificationCounts(): array
    {
        $admin = $this->admin();
        $adminId = $admin?->id;
        $taskNotifications = $adminId
            ? app(\App\Services\TaskNotificationService::class)->unreadAssignmentCountForAdmin($adminId)
            : 0;

        $documentQuery = Document::expiringSoon(30);
        $taskOverdueQuery = Task::open()->forColumn('overdue');
        $overdueCasesQuery = CredentialingCase::filterCategory('overdue');

        if ($admin && $this->scope->isPracticeScoped($admin)) {
            $ids = $this->scope->assignedPracticeIds($admin) ?? [];
            if ($ids === []) {
                $documentQuery->whereRaw('0 = 1');
                $taskOverdueQuery->whereRaw('0 = 1');
                $overdueCasesQuery->whereRaw('0 = 1');
            } else {
                $documentQuery->where(function ($q) use ($ids) {
                    $q->whereIn('practice_id', $ids)
                        ->orWhereHas('credentialingCase', fn ($cq) => $cq->whereIn('practice_id', $ids))
                        ->orWhereHas('provider.practices', fn ($pq) => $pq->whereIn('practices.id', $ids));
                });
                $taskOverdueQuery->whereHas('credentialingCase', fn ($q) => $q->whereIn('practice_id', $ids));
                $this->scope->scopeCredentialingCases($overdueCasesQuery, $admin);
            }
        }

        return [
            'expiring_documents' => $documentQuery->count(),
            'overdue_tasks' => $taskOverdueQuery->count(),
            'overdue_cases' => $overdueCasesQuery->count(),
            'task_assignments' => $taskNotifications,
        ];
    }

    public function notificationTotal(): int
    {
        $counts = $this->notificationCounts();

        return $counts['expiring_documents'] + $counts['overdue_tasks'] + $counts['overdue_cases'] + $counts['task_assignments'];
    }
}
