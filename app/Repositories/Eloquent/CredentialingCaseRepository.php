<?php

namespace App\Repositories\Eloquent;

use App\Models\CredentialingCase;
use App\Models\Status;
use App\Repositories\Contracts\CredentialingCaseRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class CredentialingCaseRepository extends EloquentRepository implements CredentialingCaseRepositoryInterface
{
    public function __construct()
    {
        parent::__construct(new CredentialingCase);
    }

    public function findOpenDuplicate(array $criteria, ?int $excludeId = null): ?CredentialingCase
    {
        $closedCategories = ['approved', 'closed'];

        $query = CredentialingCase::query()
            ->where('provider_id', $criteria['provider_id'])
            ->where('practice_id', $criteria['practice_id'])
            ->where('payer_id', $criteria['payer_id'])
            ->where('state', $criteria['state'] ?? null)
            ->when(
                ! empty($criteria['case_type_id']),
                fn ($q) => $q->where('case_type_id', $criteria['case_type_id'])
            )
            ->when(
                ! empty($criteria['provider_practice_location_id']),
                fn ($q) => $q->where('provider_practice_location_id', $criteria['provider_practice_location_id'])
            )
            ->whereHas('status', fn ($q) => $q->whereNotIn('dashboard_category', $closedCategories));

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->first();
    }

    public function filter(array $filters): LengthAwarePaginator
    {
        $query = CredentialingCase::with([
            'provider.user', 'payer', 'practice', 'status', 'delayOwner', 'assignedAdmin', 'priority',
        ])->withCount(['tasks as open_tasks_count' => fn ($q) => $q->open()]);

        if (! empty($filters['caseSearch'])) {
            $search = '%' . trim($filters['caseSearch']) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('case_number', 'like', $search)
                    ->orWhere('state', 'like', $search)
                    ->orWhereHas('provider.user', fn ($q) => $q->where('name', 'like', $search))
                    ->orWhereHas('practice', fn ($q) => $q->where('legal_name', 'like', $search)->orWhere('dba_name', 'like', $search))
                    ->orWhereHas('payer', fn ($q) => $q->where('name', 'like', $search))
                    ->orWhereHas('provider', fn ($q) => $q->where('npi', 'like', $search));
            });
        }

        if (! empty($filters['filterPayerId'])) {
            $query->where('payer_id', (int) $filters['filterPayerId']);
        }

        if (! empty($filters['filterStatusId'])) {
            $query->where('status_id', (int) $filters['filterStatusId']);
        }

        if (! empty($filters['filterOwnerId'])) {
            $query->where('assigned_admin_id', (int) $filters['filterOwnerId']);
        }

        if (! empty($filters['filterState'])) {
            $query->where('state', $filters['filterState']);
        }

        if (! empty($filters['filterCategory'])) {
            $query->filterCategory($filters['filterCategory']);
        }

        return $query->orderByDesc('last_action_at')->paginate($filters['perPage'] ?? 15);
    }

    public function forProvider(int $providerId): Collection
    {
        return CredentialingCase::with(['payer', 'status', 'delayOwner', 'assignedAdmin', 'practice'])
            ->where('provider_id', $providerId)
            ->orderByDesc('last_action_at')
            ->get();
    }
}
