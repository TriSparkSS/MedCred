<?php

namespace App\Livewire\Admin\Provider;

use App\Models\ProviderDetails;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::admin', ['title' => 'Provider Details'])]
class ProviderDetailsPage extends Component
{
    public ProviderDetails $provider;

    public function mount($provider)
    {
        $this->provider = ProviderDetails::with('user', 'specialty', 'practices.primaryAddress')->findOrFail($provider);
    }

    public function render()
    {
        return view('livewire.admin.provider.provider-details-page');
    }
}
