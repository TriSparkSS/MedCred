<?php

namespace App\Livewire\Authenticate;

use App\Models\Admin;
use App\Models\LoginLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
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
            return $this->redirectRoute('admin.dashboard', navigate: true);
        }
    }

    public function authenticate()
    {
        // $this->validate();
        // dd('Authentication logic goes here.');

        $login = trim($this->login);
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        $admin = Admin::query()->where($field, $login)->first();

        if (! Auth::guard('admin')->attempt([$field => $login, 'password' => $this->password], $this->remember)) {
            // LoginLog::recordFailure('admin', $login, request(), $admin, 'Invalid credentials');

            $this->addError('login', 'These credentials do not match our records.');

            return null;
        }

        if (request()->hasSession()) {
            request()->session()->regenerate();
        } elseif (session()->isStarted()) {
            session()->regenerate();
        }

        $admin = Auth::guard('admin')->user();

        // LoginLog::recordSuccess($admin, 'admin', $login, request());

        flash()->success('Welcome back, ' . $admin->name . '.');

        return $this->redirectRoute('admin.dashboard', navigate: true);
    }

    public function render()
    {
        return view('livewire.authenticate.login-page');
    }
}
