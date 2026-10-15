<?php

namespace App\Livewire\Authenticate;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
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

        if ((int) ($user->status ?? 1) === 0 || $user->status === 'inactive') {
            Auth::guard('web')->logout();

            return;
        }

        if ($user->providerDetails && $user->hasRole('provider')) {
            $providerStatus = $user->providerDetails->status instanceof \BackedEnum
                ? $user->providerDetails->status->value
                : (string) $user->providerDetails->status;

            if (($user->providerDetails->is_active ?? true) && $providerStatus !== 'inactive') {
                $this->redirectRoute('provider.dashboard', navigate: true);
            } else {
                Auth::guard('web')->logout();
            }

            return;
        }

        if ($user->practice && $user->hasRole('practice')) {
            $practiceStatus = $user->practice->status instanceof \BackedEnum
                ? $user->practice->status->value
                : (string) $user->practice->status;

            if ($practiceStatus === 'active') {
                $this->redirectRoute('practice.dashboard', navigate: true);
            } else {
                Auth::guard('web')->logout();
            }
        }
    }

    public function login()
    {
        return $this->authenticate();
    }

    public function authenticate()
    {
        $this->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $throttleKey = 'portal-login:' . Str::lower(trim($this->email)) . '|' . request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $this->addError('email', "Too many login attempts. Please try again in {$seconds} seconds.");

            return null;
        }

        if (! Auth::guard('web')->attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($throttleKey, 60);
            $this->addError('email', 'These credentials do not match our records.');

            return null;
        }

        $user = Auth::guard('web')->user();

        if ((int) ($user->status ?? 1) === 0 || $user->status === 'inactive') {
            Auth::guard('web')->logout();
            RateLimiter::hit($throttleKey, 60);
            $this->addError('email', 'Your account is inactive.');

            return null;
        }

        if ($user->providerDetails && $user->hasRole('provider')) {
            $providerStatus = $user->providerDetails->status instanceof \BackedEnum
                ? $user->providerDetails->status->value
                : (string) $user->providerDetails->status;

            if (! ($user->providerDetails->is_active ?? true) || $providerStatus === 'inactive') {
                Auth::guard('web')->logout();
                RateLimiter::hit($throttleKey, 60);
                $this->addError('email', 'Your provider account is inactive.');

                return null;
            }

            RateLimiter::clear($throttleKey);
            if (request()->hasSession()) {
                request()->session()->regenerate();
            } elseif (session()->isStarted()) {
                session()->regenerate();
            }
            flash()->success('Welcome back, ' . $user->name . '.');

            return $this->redirectRoute('provider.dashboard');
        }

        if ($user->practice && $user->hasRole('practice')) {
            $practiceStatus = $user->practice->status instanceof \BackedEnum
                ? $user->practice->status->value
                : (string) $user->practice->status;

            if ($practiceStatus !== 'active') {
                Auth::guard('web')->logout();
                RateLimiter::hit($throttleKey, 60);
                $this->addError('email', 'Your practice account is inactive.');

                return null;
            }

            RateLimiter::clear($throttleKey);
            if (request()->hasSession()) {
                request()->session()->regenerate();
            } elseif (session()->isStarted()) {
                session()->regenerate();
            }
            flash()->success('Welcome back, ' . $user->name . '.');

            return $this->redirectRoute('practice.dashboard');
        }

        Auth::guard('web')->logout();
        RateLimiter::hit($throttleKey, 60);
        $this->addError('email', 'This account is not linked to a provider or practice profile.');

        return null;
    }

    public function render()
    {
        return view('livewire.authenticate.portal-login-page');
    }
}
