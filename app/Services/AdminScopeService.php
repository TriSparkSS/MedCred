<?php

namespace App\Services;

use App\Enums\AdminRole;
use App\Models\Admin;
use App\Models\CredentialingCase;
use App\Models\Document;
use App\Models\ProviderDetails;
use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

class AdminScopeService
{
    public function isUnrestricted(Admin $admin): bool
    {
        return $admin->hasRole(AdminRole::SystemAdmin->value);
    }

    public function isPracticeScoped(Admin $admin): bool
    {
        return ! $this->isUnrestricted($admin);
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

    public function canAccessProvider(Admin $admin, ProviderDetails|int $provider): bool
    {
        $providerModel = is_int($provider) ? ProviderDetails::find($provider) : $provider;
        if (! $providerModel) {
            return false;
        }

        $ids = $this->assignedPracticeIds($admin);

        if ($ids === null) {
            return true;
        }

        if ($ids === []) {
            return false;
        }

        return $providerModel->practices()->whereIn('practices.id', $ids)->exists();
    }

    public function canAccessCase(Admin $admin, CredentialingCase $case): bool
    {
        return $this->canAccessPractice($admin, (int) $case->practice_id);
    }

    public function canViewRestrictedDocument(Admin $admin, Document $document): bool
    {
        if (($document->visibility ?? $document->access_level) !== 'restricted') {
            return true;
        }

        if ($this->isUnrestricted($admin) || $admin->can('admin.settings.manage') || $admin->hasRole('super_admin')) {
            return true;
        }

        if ($document->uploaded_by_admin_id && (int) $document->uploaded_by_admin_id === (int) $admin->id) {
            return true;
        }

        $allowedRoles = array_values(array_filter((array) ($document->allowed_roles ?? [])));
        if ($allowedRoles !== [] && $admin->hasAnyRole($allowedRoles)) {
            return true;
        }

        return false;
    }

    public function canAccessDocument(Admin $admin, Document $document): bool
    {
        if (! $this->canViewRestrictedDocument($admin, $document)) {
            return false;
        }

        $ids = $this->assignedPracticeIds($admin);

        if ($ids === null) {
            return true;
        }

        if ($ids === []) {
            return false;
        }

        if ($document->practice_id !== null) {
            return in_array((int) $document->practice_id, $ids, true);
        }

        if ($document->credentialing_case_id !== null) {
            $case = $document->relationLoaded('credentialingCase')
                ? $document->credentialingCase
                : $document->credentialingCase()->first();
            $practiceId = $case?->practice_id;

            return $practiceId !== null && in_array((int) $practiceId, $ids, true);
        }

        if ($document->provider_id !== null) {
            $provider = $document->relationLoaded('provider')
                ? $document->provider
                : $document->provider()->first();

            return $provider !== null && $this->canAccessProvider($admin, $provider);
        }

        return false;
    }

    public function canAccessTask(Admin $admin, Task $task): bool
    {
        $ids = $this->assignedPracticeIds($admin);

        if ($ids === null) {
            return true;
        }

        if ($ids === []) {
            return false;
        }

        if ($task->credentialing_case_id !== null) {
            $case = $task->relationLoaded('credentialingCase')
                ? $task->credentialingCase
                : $task->credentialingCase()->first();

            return $case !== null && in_array((int) $case->practice_id, $ids, true);
        }

        if ($task->provider_id !== null) {
            $provider = $task->relationLoaded('provider')
                ? $task->provider
                : $task->provider()->first();

            return $provider !== null && $this->canAccessProvider($admin, $provider);
        }

        return false;
    }

    public function scopePractices(Builder|Relation $query, Admin $admin): Builder|Relation
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

    public function scopeProviders(Builder|Relation $query, Admin $admin): Builder|Relation
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

    public function scopeCredentialingCases(Builder|Relation $query, Admin $admin): Builder|Relation
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

    public function scopeTasks(Builder|Relation $query, Admin $admin): Builder|Relation
    {
        $ids = $this->assignedPracticeIds($admin);

        if ($ids === null) {
            return $query;
        }

        if ($ids === []) {
            return $query->whereRaw('0 = 1');
        }

        $table = $query->getModel()->getTable();

        return $query->where(function (Builder $q) use ($ids, $table) {
            $q->whereHas('credentialingCase', fn (Builder $cq) => $cq->whereIn('practice_id', $ids))
                ->orWhere(function (Builder $sq) use ($ids, $table) {
                    $sq->whereNull($table.'.credentialing_case_id')
                        ->whereHas('provider.practices', fn (Builder $pq) => $pq->whereIn('practices.id', $ids));
                });
        });
    }

    public function scopeDocuments(Builder|Relation $query, Admin $admin): Builder|Relation
    {
        $table = $query->getModel()->getTable();

        if (! $this->isUnrestricted($admin) && ! $admin->can('admin.settings.manage') && ! $admin->hasRole('super_admin')) {
            $roleNames = $admin->getRoleNames()->values()->all();
            $query->where(function (Builder $vq) use ($admin, $roleNames, $table) {
                $vq->whereNull($table.'.visibility')
                    ->orWhere($table.'.visibility', '!=', 'restricted')
                    ->orWhere($table.'.uploaded_by_admin_id', $admin->id);
                foreach ($roleNames as $roleName) {
                    $vq->orWhere($table.'.allowed_roles', 'like', '%"'.$roleName.'"%');
                }
            });
        }

        $ids = $this->assignedPracticeIds($admin);

        if ($ids === null) {
            return $query;
        }

        if ($ids === []) {
            return $query->whereRaw('0 = 1');
        }

        return $query->where(function (Builder $q) use ($ids, $table) {
            $q->whereIn($table.'.practice_id', $ids)
                ->orWhere(function (Builder $sq) use ($ids, $table) {
                    $sq->whereNull($table.'.practice_id')
                        ->whereHas('credentialingCase', fn (Builder $cq) => $cq->whereIn('practice_id', $ids));
                })
                ->orWhere(function (Builder $sq) use ($ids, $table) {
                    $sq->whereNull($table.'.practice_id')
                        ->whereNull($table.'.credentialing_case_id')
                        ->whereHas('provider.practices', fn (Builder $pq) => $pq->whereIn('practices.id', $ids));
                });
        });
    }
}
