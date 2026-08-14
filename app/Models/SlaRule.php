<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SlaRule extends Model
{
    protected $table = 'sla_rules_admin';

    protected $fillable = [
        'name',
        'rule_key',
        'dashboard_category',
        'business_days',
        'action',
        'notification_template_id',
        'is_active',
    ];

    protected $casts = [
        'business_days' => 'integer',
        'is_active' => 'boolean',
    ];

    public function notificationTemplate(): BelongsTo
    {
        return $this->belongsTo(NotificationTemplate::class);
    }
}
