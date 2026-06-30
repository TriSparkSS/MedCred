<?php

namespace App\Livewire\Provider;

use App\Services\ProviderDashboardService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::provider', ['title' => 'Provider Dashboard'])]
class ProviderDashboardPage extends Component
{
    public function render(ProviderDashboardService $dashboard)
    {
        abort_unless(can_do('portal.dashboard.view'), 403);

        $provider = Auth::guard('web')->user()->providerDetails()
            ->with(['credentialingCases.status', 'credentialingCases.payer'])
            ->first();

        return view('livewire.provider.provider-dashboard-page', [
            'provider' => $provider,
            'stats' => $dashboard->stats($provider),
            'outstanding' => $dashboard->outstandingDocuments($provider)->take(10),
            'recentCases' => $provider->credentialingCases()->with(['payer', 'status'])->latest()->limit(5)->get(),
        ]);
    }
}
