<?php

namespace App\Livewire\Practice;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::practice', ['title' => 'Practice Profile'])]
class PracticeProfilePage extends Component
{
    public function render()
    {
        abort_unless(can_do('portal.profile.view'), 403);

        $practice = Auth::guard('web')->user()->practice()
            ->with(['user', 'contacts', 'locations', 'primaryAddress', 'providers'])
            ->first();

        $activeCases = $practice->credentialingCases()
            ->active()
            ->count();

        return view('livewire.practice.practice-profile-page', [
            'practice' => $practice,
            'canViewLocations' => can_do('portal.locations.view'),
            'activeCases' => $activeCases,
        ]);
    }
}
