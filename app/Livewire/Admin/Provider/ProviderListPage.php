<?php

namespace App\Livewire\Admin\Provider;

use Livewire\Component;
use Livewire\Attributes\Layout;

#[Layout('layouts::admin', ['title' => 'Providers'])]
class ProviderListPage extends Component
{
    public function render()
    {
        return view('livewire.admin.provider.provider-list-page');
    }
}
