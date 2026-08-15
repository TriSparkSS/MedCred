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
        return Admin::orderBy('name')->get()->map(function (Admin $admin) {
            $cases = CredentialingCase::where('assigned_admin_id', $admin->id);
            $this->applyCaseScope($cases);
            $closedCategories = ['approved', 'closed'];

            $approved = (clone $cases)->whereHas('status', fn ($q) => $q->where('dashboard_category', 'approved'))->count();
            $active = (clone $cases)->whereHas('status', fn ($q) => $q->whereNotIn('dashboard_category', $closedCategories))->count();

            $avgTurnaround = (clone $cases)
                ->whereHas('status', fn ($q) => $q->where('dashboard_category', 'approved'))
                ->whereNotNull('submission_date')
                ->whereNotNull('effective_date')
                ->get()
                ->avg(fn ($case) => $case->submission_date->diffInDays($case->effective_date));

            $overdueQuery = CredentialingCase::where('assigned_admin_id', $admin->id)->filterCategory('overdue');
            $this->applyCaseScope($overdueQuery);

            $taskQuery = \App\Models\Task::open()->where('assigned_admin_id', $admin->id);
            $viewer = Auth::guard('admin')->user();
            if ($viewer) {
                $this->scope->scopeTasks($taskQuery, $viewer);
            }

            return [
                'admin_id' => $admin->id,
                'name' => $admin->name,
                'active_cases' => $active,
                'approved_cases' => $approved,
                'overdue_cases' => $overdueQuery->count(),
                'open_tasks' => $taskQuery->count(),
                'avg_turnaround_days' => $avgTurnaround ? round($avgTurnaround) : null,
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
