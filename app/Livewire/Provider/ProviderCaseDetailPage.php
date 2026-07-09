<?php

namespace App\Livewire\Provider;

use App\Services\PortalCaseService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::provider', ['title' => 'Application Details'])]
class ProviderCaseDetailPage extends Component
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

        $provider = Auth::guard('web')->user()->providerDetails;
        $case = $portalCase->findProviderCase($provider, $this->caseId);
        $payload = $portalCase->caseDetailPayload($case);

        return view('livewire.provider.provider-case-detail-page', [
            'case' => $payload['case'],
            'checklist' => $payload['checklist'],
            'groupedTasks' => $payload['grouped_tasks'],
            'casesRoute' => route('provider.cases'),
            'caseShowRoute' => 'provider.cases.show',
            'documentsRoute' => route('provider.documents'),
            'showProvider' => false,
        ]);
    }
}
