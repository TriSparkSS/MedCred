<?php

namespace App\Services;

use App\Models\CredentialingCase;
use App\Models\Practice;
use Illuminate\Support\Collection;

class PracticeDashboardService
{
    public function stats(Practice $practice): array
    {
        $cases = CredentialingCase::where('practice_id', $practice->id);

        return [
            'active_cases' => (clone $cases)->active()->count(),
            'pending_action' => (clone $cases)->filterCategory('provider')->count(),
            'approved' => (clone $cases)->whereHas('status', fn ($q) => $q->where('dashboard_category', 'approved'))->count(),
            'linked_providers' => $practice->providers()->count(),
            'checklist_pending' => $this->pendingChecklistCount($practice),
        ];
    }

    public function pendingChecklistCount(Practice $practice): int
    {
        return CredentialingCase::where('practice_id', $practice->id)
            ->with('documentItems')
            ->get()
            ->sum(fn ($case) => $case->documentItems->where('is_required', true)->where('is_received', false)->count());
    }

    public function outstandingDocuments(Practice $practice): Collection
    {
        return CredentialingCase::where('practice_id', $practice->id)
            ->active()
            ->with(['payer', 'provider.user', 'documentItems.documentType'])
            ->get()
            ->flatMap(function ($case) {
                return $case->documentItems
                    ->where('is_required', true)
                    ->where('is_received', false)
                    ->map(fn ($item) => [
                        'case_id' => $case->id,
                        'case_number' => $case->case_number,
                        'payer' => $case->payer->name ?? '',
                        'provider' => $case->provider->user->name ?? '',
                        'document_type' => $item->documentType->name ?? 'Document',
                        'document_type_id' => $item->document_type_id,
                    ]);
            });
    }
}
