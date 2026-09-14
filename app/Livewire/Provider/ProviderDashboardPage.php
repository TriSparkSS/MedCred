<?php

namespace App\Livewire\Provider;

use App\Services\PortalAssignedItemsService;
use App\Services\ProviderDashboardService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::provider', ['title' => 'Provider Dashboard'])]
class ProviderDashboardPage extends Component
{
    public string $assignmentFilter = 'open';

    public function setAssignmentFilter(string $filter): void
    {
        $this->assignmentFilter = in_array($filter, ['open', 'closed'], true) ? $filter : 'open';
    }

    public function render(ProviderDashboardService $dashboard, PortalAssignedItemsService $assignedItems)
    {
        abort_unless(can_do('portal.dashboard.view'), 403);

        $provider = Auth::guard('web')->user()->providerDetails()
            ->with(['credentialingCases.status', 'credentialingCases.payer'])
            ->first();

        return view('livewire.provider.provider-dashboard-page', [
            'provider' => $provider,
            'stats' => $dashboard->stats($provider),
            'myItems' => $assignedItems->forProvider($provider, $this->assignmentFilter),
            'assignmentCounts' => $assignedItems->countsForProvider($provider),
        ]);
    }
}
