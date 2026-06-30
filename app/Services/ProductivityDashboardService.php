<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\CredentialingCase;
use Illuminate\Support\Collection;

class ProductivityDashboardService
{
    public function executiveMetrics(): Collection
    {
        return Admin::orderBy('name')->get()->map(function (Admin $admin) {
            $cases = CredentialingCase::where('assigned_admin_id', $admin->id);
            $closedCategories = ['approved', 'closed'];

            $approved = (clone $cases)->whereHas('status', fn ($q) => $q->where('dashboard_category', 'approved'))->count();
            $active = (clone $cases)->whereHas('status', fn ($q) => $q->whereNotIn('dashboard_category', $closedCategories))->count();

            $avgTurnaround = (clone $cases)
                ->whereHas('status', fn ($q) => $q->where('dashboard_category', 'approved'))
                ->whereNotNull('submission_date')
                ->whereNotNull('effective_date')
                ->get()
                ->avg(fn ($case) => $case->submission_date->diffInDays($case->effective_date));

            return [
                'admin_id' => $admin->id,
                'name' => $admin->name,
                'active_cases' => $active,
                'approved_cases' => $approved,
                'overdue_cases' => CredentialingCase::where('assigned_admin_id', $admin->id)->filterCategory('overdue')->count(),
                'open_tasks' => $admin->id ? \App\Models\Task::open()->where('assigned_admin_id', $admin->id)->count() : 0,
                'avg_turnaround_days' => $avgTurnaround ? round($avgTurnaround) : null,
            ];
        });
    }

    public function payerTurnaround(): Collection
    {
        return CredentialingCase::whereHas('status', fn ($q) => $q->where('dashboard_category', 'approved'))
            ->whereNotNull('submission_date')
            ->whereNotNull('effective_date')
            ->with('payer')
            ->get()
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
        return CredentialingCase::whereNotNull('revalidation_due_date')
            ->whereDate('revalidation_due_date', '<=', now()->addDays($days))
            ->whereDate('revalidation_due_date', '>=', now())
            ->with(['provider.user', 'payer'])
            ->orderBy('revalidation_due_date')
            ->get();
    }
}
