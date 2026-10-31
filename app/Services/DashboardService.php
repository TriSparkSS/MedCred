<?php

namespace App\Services;

use App\Enums\ProviderStatus;
use App\Enums\TaskStatus;
use App\Models\Admin;
use App\Models\CaseActivity;
use App\Models\CredentialingCase;
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

        $providerQuery = ProviderDetails::query()->where('status', ProviderStatus::ACTIVE);
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

        $revenueStuckCount = $caseQuery()
            ->where('ready_to_bill', false)
            ->whereHas('status', fn ($q) => $q->whereNotIn('dashboard_category', ['closed']))
            ->count();

        $documentQuery = Document::expiringSoon(30);
        if ($admin) {
            $this->scope->scopeDocuments($documentQuery, $admin);
        }

        $taskOverdueQuery = Task::open()->forColumn('overdue');
        if ($admin) {
            $this->scope->scopeTasks($taskOverdueQuery, $admin);
        }

        return [
            'active_providers' => $providerQuery->count(),
            'apps_in_progress' => (clone $activeCases)->count(),
            'pending_payer' => $this->scopedCategoryCount('payer', $admin),
            'pending_provider' => $this->scopedCategoryCount('provider', $admin),
            'overdue_followups' => $this->scopedCategoryCount('overdue', $admin) + $taskOverdueQuery->count(),
            'expiring_documents' => $documentQuery->count(),
            'rush_cases' => $rushCount,
            'revenue_stuck' => $revenueStuckCount,
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
        $query = CredentialingCase::active()
            ->leftJoin('delay_owners', function ($join) {
                $join->on('credentialing_cases.delay_owner_id', '=', 'delay_owners.id')
                    ->whereNull('delay_owners.deleted_at');
            });

        if ($admin) {
            $this->scope->scopeCredentialingCases($query, $admin);
        }

        $rows = $query
            ->selectRaw('credentialing_cases.delay_owner_id, delay_owners.name as owner_name, COUNT(*) as case_count')
            ->groupBy('credentialing_cases.delay_owner_id', 'delay_owners.name')
            ->get();

        $total = (int) $rows->sum('case_count');

        if ($total === 0) {
            return [
                'labels' => ['No active cases'],
                'series' => [100],
                'items' => [],
            ];
        }

        $items = $rows->map(function ($row) use ($total) {
            $count = (int) $row->case_count;

            return [
                'name' => $row->owner_name ?? 'Unassigned',
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

        if ($admin) {
            $this->scope->scopeTasks($taskQuery, $admin);
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
                'title' => 'Follow-up: '.($case->payer->name ?? 'Case'),
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

    public function myAssignments(string $status = 'open'): Collection
    {
        $admin = $this->admin();

        if (! $admin) {
            return collect();
        }

        $isOpen = $status !== 'closed';

        $caseQuery = CredentialingCase::query()
            ->where('assigned_admin_id', $admin->id)
            ->with(['provider.user', 'payer', 'status'])
            ->whereHas('status', function ($query) use ($isOpen) {
                $isOpen
                    ? $query->whereNotIn('dashboard_category', $this->closedCategories)
                    : $query->whereIn('dashboard_category', $this->closedCategories);
            });
        $this->scope->scopeCredentialingCases($caseQuery, $admin);

        $cases = $caseQuery
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (CredentialingCase $case) => [
                'type' => 'case',
                'type_label' => 'Application',
                'id' => $case->id,
                'title' => $case->case_number.($case->payer?->name ? ' · '.$case->payer->name : ''),
                'provider' => $case->provider->user->name ?? '—',
                'status' => $case->status->name ?? ($isOpen ? 'Open' : 'Closed'),
                'status_class' => $isOpen ? 'primary' : 'secondary',
                'due_date' => $case->next_follow_up_date,
                'url' => route('admin.credentials.show', $case),
            ]);

        $taskQuery = Task::query()
            ->where('assigned_admin_id', $admin->id)
            ->with(['provider.user', 'priority'])
            ->when(
                $isOpen,
                fn ($query) => $query->open(),
                fn ($query) => $query->where(function ($q) {
                    $q->whereNotNull('completed_at')
                        ->orWhereIn('status', [TaskStatus::Completed->value, TaskStatus::Cancelled->value]);
                })
            );
        $this->scope->scopeTasks($taskQuery, $admin);

        $tasks = $taskQuery
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (Task $task) => [
                'type' => 'task',
                'type_label' => 'Task',
                'id' => $task->id,
                'title' => $task->title,
                'provider' => $task->provider->user->name ?? '—',
                'status' => $task->status?->label() ?? ($isOpen ? 'Open' : 'Closed'),
                'status_class' => $task->kanbanColumn() === 'overdue' ? 'danger' : ($isOpen ? 'info' : 'secondary'),
                'due_date' => $task->due_date,
                'url' => route('admin.tasks.kanban'),
            ]);

        return $cases->concat($tasks)
            ->sortBy(fn (array $item) => $item['due_date']?->timestamp ?? PHP_INT_MAX)
            ->values();
    }

    public function myAssignmentCounts(): array
    {
        $admin = $this->admin();

        if (! $admin) {
            return ['open' => 0, 'closed' => 0];
        }

        $openCaseQuery = CredentialingCase::query()
            ->where('assigned_admin_id', $admin->id)
            ->whereHas('status', fn ($q) => $q->whereNotIn('dashboard_category', $this->closedCategories));
        $this->scope->scopeCredentialingCases($openCaseQuery, $admin);
        $openCases = $openCaseQuery->count();

        $closedCaseQuery = CredentialingCase::query()
            ->where('assigned_admin_id', $admin->id)
            ->whereHas('status', fn ($q) => $q->whereIn('dashboard_category', $this->closedCategories));
        $this->scope->scopeCredentialingCases($closedCaseQuery, $admin);
        $closedCases = $closedCaseQuery->count();

        $openTaskQuery = Task::query()->where('assigned_admin_id', $admin->id)->open();
        $this->scope->scopeTasks($openTaskQuery, $admin);
        $openTasks = $openTaskQuery->count();

        $closedTaskQuery = Task::query()
            ->where('assigned_admin_id', $admin->id)
            ->where(function ($q) {
                $q->whereNotNull('completed_at')
                    ->orWhereIn('status', [TaskStatus::Completed->value, TaskStatus::Cancelled->value]);
            });
        $this->scope->scopeTasks($closedTaskQuery, $admin);
        $closedTasks = $closedTaskQuery->count();

        return [
            'open' => $openCases + $openTasks,
            'closed' => $closedCases + $closedTasks,
        ];
    }

    public function notificationCounts(): array
    {
        $admin = $this->admin();
        $adminId = $admin?->id;
        $taskNotifications = $adminId
            ? app(TaskNotificationService::class)->unreadAssignmentCountForAdmin($adminId)
            : 0;

        $documentQuery = Document::expiringSoon(30);
        $taskOverdueQuery = Task::open()->forColumn('overdue');
        $overdueCasesQuery = CredentialingCase::filterCategory('overdue');
        $missingDocsQuery = CredentialingCase::filterCategory('provider');
        $revalidationQuery = CredentialingCase::query()
            ->whereBetween('revalidation_due_date', [now()->toDateString(), now()->addDays(90)->toDateString()]);

        if ($admin) {
            $this->scope->scopeDocuments($documentQuery, $admin);
            $this->scope->scopeTasks($taskOverdueQuery, $admin);
            $this->scope->scopeCredentialingCases($overdueCasesQuery, $admin);
            $this->scope->scopeCredentialingCases($missingDocsQuery, $admin);
            $this->scope->scopeCredentialingCases($revalidationQuery, $admin);
        }

        return [
            'expiring_documents' => $documentQuery->count(),
            'missing_documents' => $missingDocsQuery->count(),
            'revalidation_reminders' => $revalidationQuery->count(),
            'overdue_tasks' => $taskOverdueQuery->count(),
            'overdue_cases' => $overdueCasesQuery->count(),
            'task_assignments' => $taskNotifications,
        ];
    }

    public function notificationTotal(): int
    {
        $counts = $this->notificationCounts();

        return $counts['expiring_documents']
            + ($counts['missing_documents'] ?? 0)
            + ($counts['revalidation_reminders'] ?? 0)
            + $counts['overdue_tasks']
            + $counts['overdue_cases']
            + $counts['task_assignments'];
    }
}
