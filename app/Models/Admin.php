<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'username', 'avatar', 'phone', 'email', 'status', 'is_two_factor', 'email_verified_at', 'password', 'remember_token'])]
#[Hidden(['password', 'remember_token'])]
class Admin extends Authenticatable
{
    use HasRoles, Notifiable, SoftDeletes;

    protected string $guard_name = 'admin';

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_two_factor' => 'boolean',
            'password' => 'hashed',
        ];
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
        return $this->name . ' (' . $this->username . ')';
    }
}
