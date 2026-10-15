<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\CredentialingCase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class ProductivityDashboardService
{
    public function __construct(
        protected AdminScopeService $scope
    ) {}

    public function executiveMetrics(): Collection
    {
        $admins = Admin::orderBy('name')->get();

        if ($admins->isEmpty()) {
            return collect();
        }

        $adminIds = $admins->pluck('id')->all();
        $closedCategories = ['approved', 'closed'];

        $approvedCountsQuery = CredentialingCase::query()
            ->whereIn('assigned_admin_id', $adminIds)
            ->whereHas('status', fn ($q) => $q->where('dashboard_category', 'approved'));
        $this->applyCaseScope($approvedCountsQuery);
        $approvedCounts = $approvedCountsQuery
            ->selectRaw('assigned_admin_id, COUNT(*) as aggregate')
            ->groupBy('assigned_admin_id')
            ->pluck('aggregate', 'assigned_admin_id');

        $activeCountsQuery = CredentialingCase::query()
            ->whereIn('assigned_admin_id', $adminIds)
            ->whereHas('status', fn ($q) => $q->whereNotIn('dashboard_category', $closedCategories));
        $this->applyCaseScope($activeCountsQuery);
        $activeCounts = $activeCountsQuery
            ->selectRaw('assigned_admin_id, COUNT(*) as aggregate')
            ->groupBy('assigned_admin_id')
            ->pluck('aggregate', 'assigned_admin_id');

        $overdueCountsQuery = CredentialingCase::query()
            ->whereIn('assigned_admin_id', $adminIds)
            ->filterCategory('overdue');
        $this->applyCaseScope($overdueCountsQuery);
        $overdueCounts = $overdueCountsQuery
            ->selectRaw('assigned_admin_id, COUNT(*) as aggregate')
            ->groupBy('assigned_admin_id')
            ->pluck('aggregate', 'assigned_admin_id');

        $turnaroundCasesQuery = CredentialingCase::query()
            ->select(['assigned_admin_id', 'submission_date', 'effective_date'])
            ->whereIn('assigned_admin_id', $adminIds)
            ->whereHas('status', fn ($q) => $q->where('dashboard_category', 'approved'))
            ->whereNotNull('submission_date')
            ->whereNotNull('effective_date');
        $this->applyCaseScope($turnaroundCasesQuery);
        $turnaroundByAdmin = $turnaroundCasesQuery
            ->get()
            ->groupBy('assigned_admin_id')
            ->map(function (Collection $cases) {
                $avg = $cases->avg(fn ($case) => $case->submission_date->diffInDays($case->effective_date));

                return $avg ? round($avg) : null;
            });

        $taskCountsQuery = \App\Models\Task::open()
            ->whereIn('assigned_admin_id', $adminIds);
        $viewer = Auth::guard('admin')->user();
        if ($viewer) {
            $this->scope->scopeTasks($taskCountsQuery, $viewer);
        }
        $openTaskCounts = $taskCountsQuery
            ->selectRaw('assigned_admin_id, COUNT(*) as aggregate')
            ->groupBy('assigned_admin_id')
            ->pluck('aggregate', 'assigned_admin_id');

        return $admins->map(function (Admin $admin) use (
            $activeCounts,
            $approvedCounts,
            $overdueCounts,
            $openTaskCounts,
            $turnaroundByAdmin,
        ) {
            return [
                'admin_id' => $admin->id,
                'name' => $admin->name,
                'active_cases' => (int) ($activeCounts[$admin->id] ?? 0),
                'approved_cases' => (int) ($approvedCounts[$admin->id] ?? 0),
                'overdue_cases' => (int) ($overdueCounts[$admin->id] ?? 0),
                'open_tasks' => (int) ($openTaskCounts[$admin->id] ?? 0),
                'avg_turnaround_days' => $turnaroundByAdmin[$admin->id] ?? null,
            ];
        });
    }

    public function payerTurnaround(): Collection
    {
        $query = CredentialingCase::whereHas('status', fn ($q) => $q->where('dashboard_category', 'approved'))
            ->whereNotNull('submission_date')
            ->whereNotNull('effective_date')
            ->with('payer');
        $this->applyCaseScope($query);

        return $query->get()
            ->groupBy('payer_id')
            ->map(function ($cases, $payerId) {
                $payer = $cases->first()->payer;

                return [
                    'payer' => $payer->name ?? 'Unknown',
                    'approved_count' => $cases->count(),
                    'avg_days' => round($cases->avg(fn ($c) => $c->submission_date->diffInDays($c->effective_date))),
                ];
            })
            ->sortByDesc('approved_count')
            ->values();
    }

    public function recredentialingUpcoming(int $days = 90): Collection
    {
        $query = CredentialingCase::whereNotNull('revalidation_due_date')
            ->whereDate('revalidation_due_date', '<=', now()->addDays($days))
            ->whereDate('revalidation_due_date', '>=', now())
            ->with(['provider.user', 'payer'])
            ->orderBy('revalidation_due_date');
        $this->applyCaseScope($query);

        return $query->get();
    }

    protected function applyCaseScope($query): void
    {
        $admin = Auth::guard('admin')->user();
        if ($admin) {
            $this->scope->scopeCredentialingCases($query, $admin);
        }
    }
}
