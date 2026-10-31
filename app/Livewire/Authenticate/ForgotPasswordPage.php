<?php

namespace App\Livewire\Authenticate;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\MailSettingsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts::app', ['title' => 'Forgot Password'])]
class ForgotPasswordPage extends Component
{
    #[Validate('required|email|max:255')]
    public string $email = '';

    public bool $statusSent = false;

    public string $statusMessage = '';

    #[Locked]
    public string $guard = 'admin';

    #[Locked]
    public ?string $issuedToken = null;

    public function mount(?string $guard = null): void
    {
        if ($guard !== null && in_array($guard, ['web', 'portal'], true)) {
            $this->guard = 'web';
        } elseif ($guard === 'admin') {
            $this->guard = 'admin';
        } elseif (request()->routeIs('portal.password.request') || in_array(request()->query('guard'), ['portal', 'web'], true)) {
            $this->guard = 'web';
        }
    }

    public function submit(): void
    {
        $this->sendResetLink();
    }

    public function requestReset(): void
    {
        $this->sendResetLink();
    }

    public function sendResetLink(): void
    {
        $this->validate([
            'email' => 'required|email|max:255',
        ]);

        $email = Str::lower(trim($this->email));
        $throttleKey = 'forgot-password:'.$email.'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $this->addError('email', "Too many password reset requests. Please try again in {$seconds} seconds.");

            return;
        }

        RateLimiter::hit($throttleKey, 60);

        $this->issuedToken = null;
        $targetGuard = $this->guard === 'web' ? 'web' : 'admin';
        $account = $targetGuard === 'admin'
            ? Admin::query()->whereRaw('LOWER(email) = ?', [$email])->first()
            : User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if ($account) {
            $plainToken = Str::random(64);
            $this->issuedToken = app()->environment(['local', 'testing']) ? $plainToken : null;
            $tokenKey = $targetGuard.':'.Str::lower($account->email);

            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $tokenKey],
                [
                    'token' => Hash::make($plainToken),
                    'created_at' => now(),
                ]
            );

            $resetRoute = $targetGuard === 'web' ? 'portal.password.reset' : 'password.reset';
            $resetUrl = route($resetRoute, [
                'token' => $plainToken,
                'email' => $account->email,
                'guard' => $targetGuard === 'web' ? 'portal' : 'admin',
            ]);

            try {
                app(MailSettingsService::class)->applyRuntimeConfig();
                Mail::raw(
                    "You requested a password reset for your Revantage CRM account. Reset your password using this link: {$resetUrl}",
                    function ($message) use ($account) {
                        $message->to($account->email)
                            ->subject('Reset Your Revantage CRM Password');
                    }
                );
            } catch (\Throwable) {
            }

            AuditLog::record(
                $targetGuard === 'admin' ? 'admin.password_reset_requested' : 'portal.password_reset_requested',
                $account,
                $account instanceof Admin ? $account->id : null,
                [
                    'email' => $account->email,
                    'guard' => $targetGuard,
                    'ip' => request()->ip(),
                ]
            );
        }

        $this->statusSent = true;
        $this->statusMessage = 'If an account matches that email address, we have sent a password reset link.';
        flash()->success($this->statusMessage);
    }

    public function render()
    {
        return view('livewire.authenticate.forgot-password-page');
    }
}
