<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'phone', 'role', 'email_verified_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function providerDetails()
    {
        return $this->hasOne(ProviderDetails::class);
    }

    /**
     * Scope a query to only provider users.
     *
     * This includes users who have provider details or are assigned the
     * 'provider' role via Spatie permissions.
     *
     * Usage: User::providers()->get();
     */
    public function scopeProviders($query)
    {
        return $query->whereHas('providerDetails')
            ->orWhereHas('roles', function ($q) {
                $q->where('name', 'provider');
            });
    }
}
