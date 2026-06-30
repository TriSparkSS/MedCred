<?php

namespace App\Services;

use App\Models\CredentialingCase;
use App\Models\Payer;
use App\Models\ProviderDetails;
use Illuminate\Support\Collection;

class GlobalSearchService
{
    public function search(string $query, int $limitPerGroup = 5): array
    {
        $query = trim($query);

        if (strlen($query) < 2) {
            return [
                'providers' => collect(),
                'cases' => collect(),
                'payers' => collect(),
            ];
        }

        $like = '%' . $query . '%';

        return [
            'providers' => $this->searchProviders($like, $limitPerGroup),
            'cases' => $this->searchCases($like, $limitPerGroup),
            'payers' => $this->searchPayers($like, $limitPerGroup),
        ];
    }

    protected function searchProviders(string $like, int $limit): Collection
    {
        return ProviderDetails::with('user')
            ->where(function ($q) use ($like) {
                $q->where('npi', 'like', $like)
                    ->orWhereHas('user', fn ($q) => $q->where('name', 'like', $like));
            })
            ->limit($limit)
            ->get()
            ->map(fn (ProviderDetails $provider) => [
                'id' => $provider->id,
                'label' => $provider->user->name ?? 'Provider #' . $provider->id,
                'meta' => $provider->npi ? 'NPI: ' . $provider->npi : null,
                'url' => route('admin.providers.show', $provider),
            ]);
    }

    protected function searchCases(string $like, int $limit): Collection
    {
        return CredentialingCase::with(['provider.user', 'payer'])
            ->where(function ($q) use ($like) {
                $q->where('case_number', 'like', $like)
                    ->orWhereHas('provider.user', fn ($q) => $q->where('name', 'like', $like))
                    ->orWhereHas('provider', fn ($q) => $q->where('npi', 'like', $like))
                    ->orWhereHas('payer', fn ($q) => $q->where('name', 'like', $like));
            })
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (CredentialingCase $case) => [
                'id' => $case->id,
                'label' => $case->case_number,
                'meta' => ($case->provider->user->name ?? '') . ' · ' . ($case->payer->name ?? ''),
                'url' => route('admin.credentials', ['search' => $case->case_number]),
            ]);
    }

    protected function searchPayers(string $like, int $limit): Collection
    {
        return Payer::where('name', 'like', $like)
            ->limit($limit)
            ->get()
            ->map(fn (Payer $payer) => [
                'id' => $payer->id,
                'label' => $payer->name,
                'meta' => 'Payer',
                'url' => route('admin.payers.edit', $payer),
            ]);
    }

    public function hasResults(array $results): bool
    {
        return collect($results)->flatten(1)->isNotEmpty();
    }
}
