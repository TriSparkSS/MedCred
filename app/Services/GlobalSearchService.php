<?php

namespace App\Services;

use App\Models\CredentialingCase;
use App\Models\Payer;
use App\Models\Practice;
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
                'practices' => collect(),
                'cases' => collect(),
                'payers' => collect(),
            ];
        }

        $like = '%' . $query . '%';

        return [
            'providers' => $this->searchProviders($like, $limitPerGroup),
            'practices' => $this->searchPractices($like, $limitPerGroup),
            'cases' => $this->searchCases($like, $limitPerGroup),
            'payers' => $this->searchPayers($like, $limitPerGroup),
        ];
    }

    protected function searchProviders(string $like, int $limit): Collection
    {
        $query = ProviderDetails::with('user')
            ->where(function ($q) use ($like) {
                $q->where('npi', 'like', $like)
                    ->orWhere('caqh_id', 'like', $like)
                    ->orWhere('practice', 'like', $like)
                    ->orWhereHas('user', fn ($q) => $q->where('name', 'like', $like))
                    ->orWhereHas('practices', fn ($q) => $q->where('legal_name', 'like', $like)
                        ->orWhere('dba_name', 'like', $like));
            });
        $admin = auth()->guard('admin')->user();
        if ($admin) {
            app(AdminScopeService::class)->scopeProviders($query, $admin);
        }

        return $query
            ->limit($limit)
            ->get()
            ->map(function (ProviderDetails $provider) {
                $metaParts = array_filter([
                    $provider->npi ? 'NPI: ' . $provider->npi : null,
                    $provider->caqh_id ? 'CAQH: ' . $provider->caqh_id : null,
                ]);

                return [
                    'id' => $provider->id,
                    'label' => $provider->user->name ?? 'Provider #' . $provider->id,
                    'meta' => $metaParts ? implode(' · ', $metaParts) : null,
                    'url' => route('admin.providers.show', $provider),
                ];
            });
    }

    protected function searchPractices(string $like, int $limit): Collection
    {
        $query = Practice::query()
            ->where(function ($q) use ($like) {
                $q->where('legal_name', 'like', $like)
                    ->orWhere('dba_name', 'like', $like)
                    ->orWhere('ein_tin', 'like', $like)
                    ->orWhere('group_npi', 'like', $like)
                    ->orWhere('client_code', 'like', $like);
            });
        $admin = auth()->guard('admin')->user();
        if ($admin) {
            app(AdminScopeService::class)->scopePractices($query, $admin);
        }

        return $query
            ->limit($limit)
            ->get()
            ->map(fn (Practice $practice) => [
                'id' => $practice->id,
                'label' => $practice->legal_name,
                'meta' => trim(($practice->ein_tin ? 'Tax ID: ' . $practice->ein_tin : '') . ($practice->group_npi ? ' · NPI: ' . $practice->group_npi : ''), ' ·') ?: null,
                'url' => route('admin.practices.show', $practice),
            ]);
    }

    protected function searchCases(string $like, int $limit): Collection
    {
        $query = CredentialingCase::with(['provider.user', 'payer', 'practice', 'status'])
            ->where(function ($q) use ($like) {
                $q->where('case_number', 'like', $like)
                    ->orWhereHas('provider.user', fn ($q) => $q->where('name', 'like', $like))
                    ->orWhereHas('provider', fn ($q) => $q->where('npi', 'like', $like)
                        ->orWhere('caqh_id', 'like', $like))
                    ->orWhereHas('payer', fn ($q) => $q->where('name', 'like', $like))
                    ->orWhereHas('practice', fn ($q) => $q->where('legal_name', 'like', $like)
                        ->orWhere('dba_name', 'like', $like)
                        ->orWhere('ein_tin', 'like', $like)
                        ->orWhere('group_npi', 'like', $like)
                        ->orWhere('client_code', 'like', $like))
                    ->orWhereHas('status', fn ($q) => $q->where('name', 'like', $like));
            });
        $admin = auth()->guard('admin')->user();
        if ($admin) {
            app(AdminScopeService::class)->scopeCredentialingCases($query, $admin);
        }

        return $query
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (CredentialingCase $case) => [
                'id' => $case->id,
                'label' => $case->case_number,
                'meta' => ($case->provider->user->name ?? '') . ' · ' . ($case->payer->name ?? '') . ($case->status?->name ? ' (' . $case->status->name . ')' : ''),
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
