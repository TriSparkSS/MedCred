<?php

namespace App\Livewire\Practice;

use App\Services\PortalAssignedItemsService;
use App\Services\PracticeDashboardService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::practice', ['title' => 'Practice Dashboard'])]
class PracticeDashboardPage extends Component
{
    public string $assignmentFilter = 'open';

    public function setAssignmentFilter(string $filter): void
    {
        $this->assignmentFilter = in_array($filter, ['open', 'closed'], true) ? $filter : 'open';
    }

    public function render(PracticeDashboardService $dashboard, PortalAssignedItemsService $assignedItems)
    {
        abort_unless(can_do('portal.dashboard.view'), 403);

        $practice = Auth::guard('web')->user()->practice()
            ->with(['credentialingCases.status', 'credentialingCases.payer', 'credentialingCases.provider.user'])
            ->first();

        return view('livewire.practice.practice-dashboard-page', [
            'practice' => $practice,
            'stats' => $dashboard->stats($practice),
            'myItems' => $assignedItems->forPractice($practice, $this->assignmentFilter),
            'assignmentCounts' => $assignedItems->countsForPractice($practice),
        ]);
    }
}
