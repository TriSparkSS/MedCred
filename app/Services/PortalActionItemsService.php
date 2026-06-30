<?php

namespace App\Services;

use App\Models\CredentialingCase;
use App\Models\Practice;
use App\Models\ProviderDetails;

class PortalActionItemsService
{
    public function __construct(
        protected ProviderDashboardService $providerDashboard,
        protected PracticeDashboardService $practiceDashboard,
    ) {}

    public function providerActionCount(ProviderDetails $provider): int
    {
        return $this->providerDashboard->stats($provider)['pending_action']
            + $this->providerDashboard->outstandingDocuments($provider)->count();
    }

    public function practiceActionCount(Practice $practice): int
    {
        return $this->practiceDashboard->stats($practice)['pending_action']
            + $this->practiceDashboard->outstandingDocuments($practice)->count();
    }

    public function providerItems(ProviderDetails $provider): array
    {
        $outstanding = $this->providerDashboard->outstandingDocuments($provider);
        $pendingCases = CredentialingCase::where('provider_id', $provider->id)
            ->with(['payer', 'status'])
            ->filterCategory('provider')
            ->latest()
            ->get();

        $overdueCases = CredentialingCase::where('provider_id', $provider->id)
            ->with(['payer', 'status'])
            ->whereNotNull('next_follow_up_date')
            ->whereDate('next_follow_up_date', '<', now())
            ->whereHas('status', fn ($q) => $q->whereNotIn('dashboard_category', ['approved', 'closed']))
            ->get();

        return [
            'outstanding_documents' => $outstanding,
            'pending_cases' => $pendingCases,
            'overdue_followups' => $overdueCases,
            'total_count' => $this->providerActionCount($provider),
        ];
    }

    public function practiceItems(Practice $practice): array
    {
        $outstanding = $this->practiceDashboard->outstandingDocuments($practice);
        $pendingCases = CredentialingCase::where('practice_id', $practice->id)
            ->with(['payer', 'status', 'provider.user'])
            ->filterCategory('provider')
            ->latest()
            ->get();

        $overdueCases = CredentialingCase::where('practice_id', $practice->id)
            ->with(['payer', 'status', 'provider.user'])
            ->whereNotNull('next_follow_up_date')
            ->whereDate('next_follow_up_date', '<', now())
            ->whereHas('status', fn ($q) => $q->whereNotIn('dashboard_category', ['approved', 'closed']))
            ->get();

        return [
            'outstanding_documents' => $outstanding,
            'pending_cases' => $pendingCases,
            'overdue_followups' => $overdueCases,
            'total_count' => $this->practiceActionCount($practice),
        ];
    }
}
