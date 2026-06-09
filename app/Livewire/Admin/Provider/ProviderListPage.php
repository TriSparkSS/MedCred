<?php

namespace App\Livewire\Admin\Provider;

use Livewire\Component;
use Livewire\Attributes\Layout;

#[Layout('layouts::admin', ['title' => 'Providers'])]
class ProviderListPage extends Component
{
    public function render()
    {
        $specialties = \App\Models\Specialty::all();
        return view('livewire.admin.provider.provider-list-page', compact('specialties'));
    }
}
