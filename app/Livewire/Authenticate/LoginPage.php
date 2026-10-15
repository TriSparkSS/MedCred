<?php

namespace App\Livewire\Authenticate;

use App\Models\Admin;
use App\Models\LoginLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts::app', ['title' => 'Login'])]
class LoginPage extends Component
{
    #[Validate('required|string|max:255')]
    public string $login = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = false;

    public function mount()
    {
        if (Auth::guard('admin')->check()) {
            $admin = Auth::guard('admin')->user();
            if (! filled($admin?->status) || $admin->status === 'active') {
                return $this->redirectRoute('admin.dashboard', navigate: true);
            }

            Auth::guard('admin')->logout();
        }
    }

    public function login()
    {
        return $this->authenticate();
    }

    public function authenticate()
    {
        $this->validate();

        $login = trim($this->login);
        $throttleKey = 'admin-login:' . Str::lower($login) . '|' . request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $this->addError('login', "Too many login attempts. Please try again in {$seconds} seconds.");

            return null;
        }

        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        $admin = Admin::query()->where($field, $login)->first();

        if ($admin && filled($admin->status) && $admin->status !== 'active') {
            RateLimiter::hit($throttleKey, 60);
            $this->addError('login', 'Your account is inactive. Please contact the administrator.');

            return null;
        }

        if (! Auth::guard('admin')->attempt([$field => $login, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($throttleKey, 60);
            // LoginLog::recordFailure('admin', $login, request(), $admin, 'Invalid credentials');

            $this->addError('login', 'These credentials do not match our records.');

            return null;
        }

        $admin = Auth::guard('admin')->user();

        if (filled($admin?->status) && $admin->status !== 'active') {
            Auth::guard('admin')->logout();
            RateLimiter::hit($throttleKey, 60);
            $this->addError('login', 'Your account is inactive. Please contact the administrator.');

            return null;
        }

        RateLimiter::clear($throttleKey);

        if (request()->hasSession()) {
            request()->session()->regenerate();
        } elseif (session()->isStarted()) {
            session()->regenerate();
        }

        // LoginLog::recordSuccess($admin, 'admin', $login, request());

        flash()->success('Welcome back, ' . $admin->name . '.');

        return $this->redirectRoute('admin.dashboard');
    }

    public function render()
    {
        return view('livewire.authenticate.login-page');
    }
}
