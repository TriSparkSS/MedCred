<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SlaRule extends Model
{
    protected $fillable = [
        'rule_key',
        'name',
        'days',
        'business_days_only',
        'applies_to',
        'action',
        'notification_template_id',
        'is_active',
    ];

    protected $casts = [
        'business_days_only' => 'boolean',
        'is_active' => 'boolean',
        'days' => 'integer',
    ];

    public function notificationTemplate(): BelongsTo
    {
        return $this->belongsTo(NotificationTemplate::class);
    }
}
