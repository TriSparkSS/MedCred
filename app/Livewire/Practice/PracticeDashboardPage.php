<?php

namespace App\Livewire\Practice;

use App\Services\PracticeDashboardService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::practice', ['title' => 'Practice Dashboard'])]
class PracticeDashboardPage extends Component
{
    public function render(PracticeDashboardService $dashboard)
    {
        abort_unless(can_do('portal.dashboard.view'), 403);

        $practice = Auth::guard('web')->user()->practice()
            ->with(['credentialingCases.status', 'credentialingCases.payer', 'credentialingCases.provider.user'])
            ->first();

        return view('livewire.practice.practice-dashboard-page', [
            'practice' => $practice,
            'stats' => $dashboard->stats($practice),
            'outstanding' => $dashboard->outstandingDocuments($practice)->take(10),
            'recentCases' => $practice->credentialingCases()->with(['payer', 'status', 'provider.user'])->latest()->limit(5)->get(),
        ]);
    }
}
