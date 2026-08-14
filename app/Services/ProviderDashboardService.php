<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\CredentialingCase;
use App\Models\ProviderDetails;
use App\Models\Task;
use Illuminate\Support\Collection;

class ProviderDashboardService
{
    public function stats(ProviderDetails $provider): array
    {
        $cases = CredentialingCase::where('provider_id', $provider->id);

        return [
            'active_cases' => (clone $cases)->active()->count(),
            'pending_action' => (clone $cases)->filterCategory('provider')->count(),
            'approved' => (clone $cases)->whereHas('status', fn ($q) => $q->where('dashboard_category', 'approved'))->count(),
            'documents_expiring' => $provider->documents()->expiringSoon(30)->count(),
            'checklist_pending' => $this->pendingChecklistCount($provider),
        ];
    }

    public function pendingChecklistCount(ProviderDetails $provider): int
    {
        return CredentialingCase::where('provider_id', $provider->id)
            ->with('documentItems')
            ->get()
            ->sum(fn ($case) => $case->documentItems->where('is_required', true)->where('is_received', false)->count());
    }

    public function outstandingDocuments(ProviderDetails $provider): Collection
    {
        return CredentialingCase::where('provider_id', $provider->id)
            ->active()
            ->with(['payer', 'documentItems.documentType'])
            ->get()
            ->flatMap(function ($case) {
                return $case->documentItems
                    ->where('is_required', true)
                    ->where('is_received', false)
                    ->map(fn ($item) => [
                        'case_id' => $case->id,
                        'case_number' => $case->case_number,
                        'payer' => $case->payer->name ?? '',
                        'document_type' => $item->documentType->name ?? 'Document',
                        'document_type_id' => $item->document_type_id,
                    ]);
            });
    }
}
