<?php

namespace App\Livewire\Admin\Analytics;

use App\Services\ProductivityDashboardService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::admin', ['title' => 'Productivity Analytics'])]
class ProductivityDashboardPage extends Component
{
    public function render(ProductivityDashboardService $service)
    {
        return view('livewire.admin.analytics.productivity-dashboard-page', [
            'executives' => $service->executiveMetrics(),
            'payerTurnaround' => $service->payerTurnaround(),
            'recredentialing' => $service->recredentialingUpcoming(),
        ]);
    }
}
