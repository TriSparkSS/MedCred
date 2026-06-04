<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Admin extends Authenticatable
{
    use Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'username',
        'avatar',
        'phone',
        'email',
        'is_two_factor',
        'email_verified_at',
        'password',
        'remember_token',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function loginLogs(): MorphMany
    {
        return $this->morphMany(LoginLog::class, 'authenticatable');
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_two_factor' => 'boolean',
            'password' => 'hashed',
        ];
    }
}
