<?php

namespace App\Services;

use App\Enums\AdminRole;
use App\Models\Admin;
use App\Models\CredentialingCase;
use App\Models\Practice;
use App\Models\ProviderDetails;
use Illuminate\Database\Eloquent\Builder;

class AdminScopeService
{
    public function isUnrestricted(Admin $admin): bool
    {
        if ($admin->roles()->count() === 0) {
            return true;
        }

        return $admin->hasRole(AdminRole::SystemAdmin->value);
    }

    public function isPracticeScoped(Admin $admin): bool
    {
        if ($this->isUnrestricted($admin)) {
            return false;
        }

        return $admin->hasAnyRole([
            AdminRole::BillingReadonly->value,
            AdminRole::CredentialingExecutive->value,
            AdminRole::CredentialingManager->value,
        ]);
    }

    /** @return list<int>|null null = unrestricted (all practices) */
    public function assignedPracticeIds(Admin $admin): ?array
    {
        if (! $this->isPracticeScoped($admin)) {
            return null;
        }

        return $admin->practices()->pluck('practices.id')->all();
    }

    public function canAccessPractice(Admin $admin, int $practiceId): bool
    {
        $ids = $this->assignedPracticeIds($admin);

        if ($ids === null) {
            return true;
        }

        return in_array($practiceId, $ids, true);
    }

    public function canAccessProvider(Admin $admin, ProviderDetails $provider): bool
    {
        $ids = $this->assignedPracticeIds($admin);

        if ($ids === null) {
            return true;
        }

        if ($ids === []) {
            return false;
        }

        return $provider->practices()->whereIn('practices.id', $ids)->exists();
    }

    public function canAccessCase(Admin $admin, CredentialingCase $case): bool
    {
        return $this->canAccessPractice($admin, (int) $case->practice_id);
    }

    public function scopePractices(Builder $query, Admin $admin): Builder
    {
        $ids = $this->assignedPracticeIds($admin);

        if ($ids === null) {
            return $query;
        }

        if ($ids === []) {
            return $query->whereRaw('0 = 1');
        }

        return $query->whereIn('practices.id', $ids);
    }

    public function scopeProviders(Builder $query, Admin $admin): Builder
    {
        $ids = $this->assignedPracticeIds($admin);

        if ($ids === null) {
            return $query;
        }

        if ($ids === []) {
            return $query->whereRaw('0 = 1');
        }

        return $query->whereHas('practices', fn (Builder $q) => $q->whereIn('practices.id', $ids));
    }

    public function scopeCredentialingCases(Builder $query, Admin $admin): Builder
    {
        $ids = $this->assignedPracticeIds($admin);

        if ($ids === null) {
            return $query;
        }

        if ($ids === []) {
            return $query->whereRaw('0 = 1');
        }

        return $query->whereIn($query->getModel()->getTable().'.practice_id', $ids);
    }

    public function scopeTasks(Builder $query, Admin $admin): Builder
    {
        $ids = $this->assignedPracticeIds($admin);

        if ($ids === null) {
            return $query;
        }

        if ($ids === []) {
            return $query->whereRaw('0 = 1');
        }

        return $query->where(function (Builder $q) use ($ids) {
            $q->whereHas('credentialingCase', fn (Builder $cq) => $cq->whereIn('practice_id', $ids))
                ->orWhereHas('provider.practices', fn (Builder $pq) => $pq->whereIn('practices.id', $ids));
        });
    }

    public function scopeDocuments(Builder $query, Admin $admin): Builder
    {
        $ids = $this->assignedPracticeIds($admin);

        if ($ids === null) {
            return $query;
        }

        if ($ids === []) {
            return $query->whereRaw('0 = 1');
        }

        return $query->where(function (Builder $q) use ($ids) {
            $q->whereIn('practice_id', $ids)
                ->orWhereHas('credentialingCase', fn (Builder $cq) => $cq->whereIn('practice_id', $ids))
                ->orWhereHas('provider.practices', fn (Builder $pq) => $pq->whereIn('practices.id', $ids));
        });
    }
}
