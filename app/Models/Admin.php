<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'username', 'avatar', 'phone', 'email', 'status', 'is_two_factor', 'two_factor_code', 'two_factor_expires_at', 'failed_login_attempts', 'locked_until', 'email_verified_at', 'password', 'password_changed_at', 'remember_token'])]
#[Hidden(['password', 'remember_token', 'two_factor_code'])]
class Admin extends Authenticatable
{
    use HasApiTokens, HasRoles, Notifiable, SoftDeletes;

    protected string $guard_name = 'admin';

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_two_factor' => 'boolean',
            'two_factor_expires_at' => 'datetime',
            'failed_login_attempts' => 'integer',
            'locked_until' => 'datetime',
            'password' => 'hashed',
            'password_changed_at' => 'datetime',
        ];
    }

    public function isLockedOut(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeAssignable($query)
    {
        return $query->active()->orderBy('name');
    }

    public function displayLabel(): string
    {
        return $this->name.' ('.$this->username.')';
    }

    public function practices(): BelongsToMany
    {
        return $this->belongsToMany(Practice::class, 'admin_practice')->withTimestamps();
    }
}
