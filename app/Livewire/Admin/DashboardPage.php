<?php

namespace App\Livewire\Admin;

use App\Services\DashboardService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::admin', ['title' => 'Dashboard'])]
class DashboardPage extends Component
{
    public function render(DashboardService $dashboard)
    {
        return view('livewire.admin.dashboard-page', [
            'stats' => $dashboard->stats(),
            'delayBreakdown' => $dashboard->delayBreakdown(),
            'workQueue' => $dashboard->workQueue(),
            'recentActivity' => $dashboard->recentActivity(),
        ]);
    }
}
