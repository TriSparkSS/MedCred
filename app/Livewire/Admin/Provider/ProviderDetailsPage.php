<?php

namespace App\Livewire\Admin\Provider;

use App\Models\ProviderDetails;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::admin', ['title' => 'Provider Details'])]
class ProviderDetailsPage extends Component
{
    public $provider;

    public $activeTab = 'overview';

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function mount($provider): void
    {
        $this->provider = ProviderDetails::with([
            'user', 'specialty', 'practices.primaryAddress',
            'credentialingCases.payer',
            'credentialingCases.status',
            'credentialingCases.delayOwner',
            'credentialingCases.assignedAdmin',
            'credentialingCases.practice',
            'credentialingCases.activities.admin',
            'credentialingCases.activities.user',
            'credentialingCases.activities.credentialingCase',
            'documents.documentType',
            'documents.versions' => fn ($q) => $q->where('is_current', true),
        ])->findOrFail($provider);
    }

    public function render()
    {
        $timelineActivities = $this->provider->credentialingCases
            ->flatMap(fn ($case) => $case->activities)
            ->sortByDesc('created_at')
            ->take(50);

        return view('livewire.admin.provider.provider-details-page', [
            'timelineActivities' => $timelineActivities,
        ]);
    }
}
