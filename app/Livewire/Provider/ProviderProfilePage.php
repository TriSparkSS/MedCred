<?php

namespace App\Livewire\Provider;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::provider', ['title' => 'My Profile'])]
class ProviderProfilePage extends Component
{
    public function render()
    {
        abort_unless(can_do('portal.profile.view'), 403);

        $provider = Auth::guard('web')->user()->providerDetails()
            ->with(['user', 'specialty', 'credentialingCases.status'])
            ->first();

        $activeCases = $provider->credentialingCases
            ->filter(fn ($c) => ! in_array($c->status?->dashboard_category ?? '', ['approved', 'closed']))
            ->count();

        return view('livewire.provider.provider-profile-page', compact('provider', 'activeCases'));
    }
}
