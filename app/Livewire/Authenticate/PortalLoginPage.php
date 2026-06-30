<?php

namespace App\Livewire\Authenticate;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts::app', ['title' => 'Credentialing Portal Login'])]
class PortalLoginPage extends Component
{
    #[Validate('required|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = false;

    public function mount(): void
    {
        if (! Auth::guard('web')->check()) {
            return;
        }

        $user = Auth::guard('web')->user();

        if ($user->providerDetails && $user->hasRole('provider')) {
            $this->redirectRoute('provider.dashboard', navigate: true);
        }

        if ($user->practice && $user->hasRole('practice')) {
            $this->redirectRoute('practice.dashboard', navigate: true);
        }
    }

    public function authenticate()
    {
        if (! Auth::guard('web')->attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            $this->addError('email', 'These credentials do not match our records.');

            return null;
        }

        $user = Auth::guard('web')->user();

        if ($user->providerDetails && $user->hasRole('provider')) {
            request()->session()->regenerate();
            flash()->success('Welcome back, ' . $user->name . '.');

            return $this->redirectRoute('provider.dashboard');
        }

        if ($user->practice && $user->hasRole('practice')) {
            request()->session()->regenerate();
            flash()->success('Welcome back, ' . $user->name . '.');

            return $this->redirectRoute('practice.dashboard');
        }

        Auth::guard('web')->logout();
        $this->addError('email', 'This account is not linked to a provider or practice profile.');

        return null;
    }

    public function render()
    {
        return view('livewire.authenticate.portal-login-page');
    }
}
