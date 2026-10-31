<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'phone', 'role', 'status', 'email_verified_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

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

    public function practice()
    {
        return $this->hasOne(Practice::class);
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
        return $query->where(function ($q) {
            $q->whereHas('providerDetails')
                ->orWhereHas('roles', function ($roleQuery) {
                    $roleQuery->where('name', 'provider');
                });
        });
    }

    public function scopePractices($query)
    {
        return $query->where(function ($q) {
            $q->whereHas('practice')
                ->orWhereHas('roles', function ($roleQuery) {
                    $roleQuery->where('name', 'practice');
                });
        });
    }
}
