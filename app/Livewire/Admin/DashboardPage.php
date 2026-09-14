<?php

namespace App\Livewire\Admin;

use App\Services\DashboardService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::admin', ['title' => 'Dashboard'])]
class DashboardPage extends Component
{
    public string $assignmentFilter = 'open';

    public function setAssignmentFilter(string $filter): void
    {
        $this->assignmentFilter = in_array($filter, ['open', 'closed'], true) ? $filter : 'open';
    }

    public function render(DashboardService $dashboard)
    {
        return view('livewire.admin.dashboard-page', [
            'stats' => $dashboard->stats(),
            'delayBreakdown' => $dashboard->delayBreakdown(),
            'myAssignments' => $dashboard->myAssignments($this->assignmentFilter),
            'assignmentCounts' => $dashboard->myAssignmentCounts(),
            'recentActivity' => $dashboard->recentActivity(),
        ]);
    }
}
