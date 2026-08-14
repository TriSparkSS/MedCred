<?php

namespace App\Repositories\Contracts;

use App\Models\ProviderDetails;
use Illuminate\Support\Collection;

interface ProviderRepositoryInterface extends RepositoryInterface
{
    public function findWithRelations(int $id, array $relations = []): ?ProviderDetails;

    public function search(string $term, int $limit = 50): Collection;

    public function findDuplicateByNpi(string $npi, ?int $excludeId = null): ?ProviderDetails;
}
