<?php

namespace App\Livewire\Admin\Credential;

use Livewire\Component;
use Livewire\Attributes\Layout;

#[Layout('layouts::admin', ['title' => 'Credentials'])]
class CredentialListPage extends Component
{
    public function render()
    {
        return view('livewire.admin.credential.credential-list-page');
    }
}
