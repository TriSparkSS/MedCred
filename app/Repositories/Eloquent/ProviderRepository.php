<?php

namespace App\Repositories\Eloquent;

use App\Models\ProviderDetails;
use App\Repositories\Contracts\ProviderRepositoryInterface;
use Illuminate\Support\Collection;

class ProviderRepository extends EloquentRepository implements ProviderRepositoryInterface
{
    public function __construct()
    {
        parent::__construct(new ProviderDetails);
    }

    public function findWithRelations(int $id, array $relations = []): ?ProviderDetails
    {
        return ProviderDetails::with($relations)->find($id);
    }

    public function search(string $term, int $limit = 50): Collection
    {
        $like = '%' . trim($term) . '%';

        return ProviderDetails::query()
            ->with(['user', 'specialty'])
            ->where(function ($q) use ($like) {
                $q->where('npi', 'like', $like)
                    ->orWhereHas('user', fn ($q) => $q->where('name', 'like', $like));
            })
            ->limit($limit)
            ->get();
    }

    public function findDuplicateByNpi(string $npi, ?int $excludeId = null): ?ProviderDetails
    {
        $query = ProviderDetails::where('npi', $npi);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->first();
    }
}
