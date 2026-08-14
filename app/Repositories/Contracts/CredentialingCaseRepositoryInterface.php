<?php

namespace App\Repositories\Contracts;

use App\Models\CredentialingCase;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface CredentialingCaseRepositoryInterface extends RepositoryInterface
{
    public function findOpenDuplicate(array $criteria, ?int $excludeId = null): ?CredentialingCase;

    public function filter(array $filters): LengthAwarePaginator;

    public function forProvider(int $providerId): Collection;
}
