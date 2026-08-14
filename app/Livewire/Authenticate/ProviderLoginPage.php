<?php

namespace App\Livewire\Authenticate;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts::app', ['title' => 'Provider Login'])]
class ProviderLoginPage extends Component
{
    #[Validate('required|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = false;

    public function mount(): void
    {
        $this->redirectRoute('portal.login', navigate: true);
    }

    public function authenticate()
    {
        return $this->redirectRoute('portal.login');
    }

    public function render()
    {
        return view('livewire.authenticate.provider-login-page');
    }
}
