<?php

namespace App\Livewire\Practice;

use App\Services\PortalCaseService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::practice', ['title' => 'Application Details'])]
class PracticeCaseDetailPage extends Component
{
    public int $caseId;

    public string $activeTab = 'overview';

    public function mount(int $case): void
    {
        abort_unless(can_do('portal.cases.view'), 403);
        $this->caseId = $case;
    }

    public function setTab(string $tab): void
    {
        if (in_array($tab, ['overview', 'documents', 'tasks', 'timeline'], true)) {
            $this->activeTab = $tab;
        }
    }

    public function render(PortalCaseService $portalCase)
    {
        abort_unless(can_do('portal.cases.view'), 403);

        $practice = Auth::guard('web')->user()->practice;
        $case = $portalCase->findPracticeCase($practice, $this->caseId);
        $payload = $portalCase->caseDetailPayload($case);

        return view('livewire.practice.practice-case-detail-page', [
            'case' => $payload['case'],
            'checklist' => $payload['checklist'],
            'groupedTasks' => $payload['grouped_tasks'],
            'casesRoute' => route('practice.cases'),
            'caseShowRoute' => 'practice.cases.show',
            'documentsRoute' => route('practice.documents'),
            'showProvider' => true,
        ]);
    }
}
