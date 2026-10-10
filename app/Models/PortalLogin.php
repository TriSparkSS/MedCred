<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PortalLogin extends Model
{
    protected $fillable = [
        'portal_name',
        'username',
        'password',
        'mfa_email',
        'mfa_phone',
        'security_notes',
        'account_details',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'password' => 'encrypted',
    ];

    public function loginable(): MorphTo
    {
        return $this->morphTo();
    }
}
