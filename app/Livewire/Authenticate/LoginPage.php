<?php

namespace App\Livewire\Authenticate;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\LoginLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
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

    public bool $awaitingTwoFactor = false;

    public ?int $pendingAdminId = null;

    public string $twoFactorCode = '';

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
        $this->validate([
            'login' => 'required|string|max:255',
            'password' => 'required|string',
        ]);

        $login = trim($this->login);
        $throttleKey = 'admin-login:' . Str::lower($login) . '|' . request()->ip();

        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        $admin = Admin::query()->where($field, $login)->first();

        if ($admin && $admin->isLockedOut()) {
            $minutes = max(1, (int) ceil(now()->diffInSeconds($admin->locked_until) / 60));
            $message = "Account is locked due to multiple failed login attempts. Try again in {$minutes} minute(s).";
            if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
                $message .= ' Too many login attempts.';
            }
            $this->addError('login', $message);

            return null;
        }

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $this->addError('login', "Too many login attempts. Please try again in {$seconds} seconds.");

            return null;
        }

        if ($admin && filled($admin->status) && $admin->status !== 'active') {
            RateLimiter::hit($throttleKey, 60);
            $this->addError('login', 'Your account is inactive. Please contact the administrator.');

            return null;
        }

        if (! $admin || ! Hash::check($this->password, $admin->password)) {
            RateLimiter::hit($throttleKey, 60);

            if ($admin) {
                $attempts = ((int) $admin->failed_login_attempts) + 1;
                $updates = ['failed_login_attempts' => $attempts];

                if ($attempts >= 5) {
                    $updates['locked_until'] = now()->addMinutes(15);
                }

                $admin->update($updates);

                if ($attempts >= 5) {
                    AuditLog::record('admin.locked_out', $admin, $admin->id, [
                        'failed_login_attempts' => $attempts,
                        'ip' => request()->ip(),
                    ]);
                }
            }

            try {
                if (class_exists(LoginLog::class)) {
                    LoginLog::recordFailure('admin', $login, request(), $admin, 'Invalid credentials');
                }
                AuditLog::record('admin.login_failed', $admin, $admin?->id, [
                    'login' => $login,
                    'ip' => request()->ip(),
                ]);
            } catch (\Throwable) {
            }

            $this->addError('login', 'These credentials do not match our records.');

            return null;
        }

        $admin->update([
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ]);

        RateLimiter::clear($throttleKey);

        if ($admin->is_two_factor) {
            $code = (string) random_int(100000, 999999);
            $admin->update([
                'two_factor_code' => $code,
                'two_factor_expires_at' => now()->addMinutes(10),
            ]);

            try {
                if (filled($admin->email)) {
                    Mail::raw(
                        "Your Revantage CRM two-factor verification code is: {$code}. It expires in 10 minutes.",
                        function ($message) use ($admin) {
                            $message->to($admin->email)
                                ->subject('Your Two-Factor Verification Code');
                        }
                    );
                }
            } catch (\Throwable) {
            }

            AuditLog::record('admin.2fa_challenged', $admin, $admin->id, [
                'ip' => request()->ip(),
            ]);

            $this->pendingAdminId = $admin->id;
            $this->awaitingTwoFactor = true;
            $this->twoFactorCode = '';

            return null;
        }

        Auth::guard('admin')->login($admin, $this->remember);

        if (request()->hasSession()) {
            request()->session()->regenerate();
        } elseif (session()->isStarted()) {
            session()->regenerate();
        }

        try {
            if (class_exists(LoginLog::class)) {
                LoginLog::recordSuccess($admin, 'admin', $login, request());
            }
        } catch (\Throwable) {
        }

        AuditLog::record('admin.login', $admin, $admin->id, [
            'ip' => request()->ip(),
        ]);

        flash()->success('Welcome back, ' . $admin->name . '.');

        return $this->redirectRoute('admin.dashboard');
    }

    public function verifyTwoFactor()
    {
        $this->validate([
            'twoFactorCode' => 'required|digits:6',
        ]);

        $admin = $this->pendingAdminId ? Admin::find($this->pendingAdminId) : null;

        if (
            ! $admin
            || ! filled($admin->two_factor_code)
            || ! hash_equals((string) $admin->two_factor_code, trim($this->twoFactorCode))
            || ! $admin->two_factor_expires_at
            || ! $admin->two_factor_expires_at->isFuture()
        ) {
            $this->addError('twoFactorCode', 'Invalid or expired verification code.');

            return null;
        }

        $admin->update([
            'two_factor_code' => null,
            'two_factor_expires_at' => null,
        ]);

        $this->awaitingTwoFactor = false;
        $this->pendingAdminId = null;
        $this->twoFactorCode = '';

        Auth::guard('admin')->login($admin, $this->remember);

        if (request()->hasSession()) {
            request()->session()->regenerate();
        } elseif (session()->isStarted()) {
            session()->regenerate();
        }

        try {
            if (class_exists(LoginLog::class)) {
                LoginLog::recordSuccess($admin, 'admin', $admin->email ?? $admin->username, request());
            }
        } catch (\Throwable) {
        }

        AuditLog::record('admin.login_2fa', $admin, $admin->id, [
            'ip' => request()->ip(),
        ]);

        flash()->success('Welcome back, ' . $admin->name . '.');

        return $this->redirectRoute('admin.dashboard');
    }

    public function cancelTwoFactor(): void
    {
        if ($this->pendingAdminId) {
            Admin::whereKey($this->pendingAdminId)->update([
                'two_factor_code' => null,
                'two_factor_expires_at' => null,
            ]);
        }

        $this->awaitingTwoFactor = false;
        $this->pendingAdminId = null;
        $this->twoFactorCode = '';
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.authenticate.login-page');
    }
}
