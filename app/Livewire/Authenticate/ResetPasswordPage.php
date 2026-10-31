<?php

namespace App\Livewire\Authenticate;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts::app', ['title' => 'Reset Password'])]
class ResetPasswordPage extends Component
{
    public string $token = '';

    #[Url]
    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    #[Locked]
    public string $guard = 'admin';

    public function mount(string $token = '', ?string $guard = null): void
    {
        $this->token = $token;
        if ($this->email === '' && request()->filled('email')) {
            $this->email = (string) request()->query('email');
        }
        if ($guard !== null && in_array($guard, ['web', 'portal'], true)) {
            $this->guard = 'web';
        } elseif ($guard === 'admin') {
            $this->guard = 'admin';
        } elseif (request()->routeIs('portal.password.reset') || in_array(request()->query('guard'), ['portal', 'web'], true)) {
            $this->guard = 'web';
        }
    }

    public function submit()
    {
        return $this->resetPassword();
    }

    public function resetPassword()
    {
        $this->validate([
            'token' => 'required|string',
            'email' => 'required|email|max:255',
            'password' => ['required', 'string', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
        ]);

        $email = Str::lower(trim($this->email));
        $targetGuard = $this->guard === 'web' ? 'web' : 'admin';
        $tokenKey = $targetGuard.':'.$email;

        $record = DB::table('password_reset_tokens')
            ->whereRaw('LOWER(email) = ?', [$tokenKey])
            ->first();

        if (! $record) {
            $otherGuardHasEmail = $targetGuard === 'admin'
                ? User::query()->whereRaw('LOWER(email) = ?', [$email])->exists()
                : Admin::query()->whereRaw('LOWER(email) = ?', [$email])->exists();

            if (! $otherGuardHasEmail) {
                $record = DB::table('password_reset_tokens')
                    ->whereRaw('LOWER(email) = ?', [$email])
                    ->first();
            }
        }

        if (! $record) {
            $this->addError('email', 'This password reset token is invalid.');

            return null;
        }

        if ($record->created_at && Carbon::parse($record->created_at)->addMinutes(60)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $record->email)->delete();
            $this->addError('email', 'This password reset token has expired.');

            return null;
        }

        $tokenMatches = Hash::check($this->token, (string) $record->token);

        if (! $tokenMatches) {
            $this->addError('email', 'This password reset token is invalid.');

            return null;
        }

        $account = $targetGuard === 'admin'
            ? Admin::query()->whereRaw('LOWER(email) = ?', [$email])->first()
            : User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if (! $account) {
            $this->addError('email', 'We could not find a user with that email address.');

            return null;
        }

        if ($account instanceof Admin) {
            $account->update([
                'password' => $this->password,
                'password_changed_at' => now(),
                'failed_login_attempts' => 0,
                'locked_until' => null,
                'remember_token' => Str::random(60),
            ]);
        } else {
            $account->update([
                'password' => $this->password,
                'remember_token' => Str::random(60),
            ]);
        }

        DB::table('password_reset_tokens')->where('email', $record->email)->delete();

        AuditLog::record(
            $targetGuard === 'admin' ? 'admin.password_reset_completed' : 'portal.password_reset_completed',
            $account,
            $account instanceof Admin ? $account->id : null,
            [
                'email' => $account->email,
                'guard' => $targetGuard,
                'ip' => request()->ip(),
            ]
        );

        flash()->success('Your password has been reset. You may now sign in.');

        return $this->redirectRoute($targetGuard === 'web' ? 'portal.login' : 'login');
    }

    public function render()
    {
        return view('livewire.authenticate.reset-password-page');
    }
}
