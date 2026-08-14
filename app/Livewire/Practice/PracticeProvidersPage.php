<?php

namespace App\Livewire\Practice;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::practice', ['title' => 'Linked Providers'])]
class PracticeProvidersPage extends Component
{
    public function render()
    {
        abort_unless(can_do('portal.providers.view'), 403);

        $practice = Auth::guard('web')->user()->practice()
            ->with(['providers.user', 'providers.specialty', 'providers.credentialingCases.status'])
            ->first();

        $activeCases = $practice->credentialingCases()->active()->count();
        $approvedProviders = $practice->providers->filter(fn ($p) => ($p->status->value ?? $p->status) === 'approved')->count();

        return view('livewire.practice.practice-providers-page', compact('practice', 'activeCases', 'approvedProviders'));
    }
}
