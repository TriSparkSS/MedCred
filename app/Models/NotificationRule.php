<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationRule extends Model
{
    protected $fillable = ['name', 'event_key', 'channel', 'recipient_roles', 'is_active'];

    protected $casts = [
        'recipient_roles' => 'array',
        'is_active' => 'boolean',
    ];
}
