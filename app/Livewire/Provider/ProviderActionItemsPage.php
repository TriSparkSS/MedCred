<?php

namespace App\Livewire\Provider;

use App\Services\PortalActionItemsService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::provider', ['title' => 'Action Items'])]
class ProviderActionItemsPage extends Component
{
    public function render(PortalActionItemsService $actionItems)
    {
        abort_unless(can_do('portal.action_items.view'), 403);

        $provider = Auth::guard('web')->user()->providerDetails()
            ->with(['credentialingCases.status', 'credentialingCases.payer'])
            ->first();

        $items = $actionItems->providerItems($provider);

        return view('livewire.provider.provider-action-items-page', [
            'provider' => $provider,
            'items' => $items,
        ]);
    }
}
