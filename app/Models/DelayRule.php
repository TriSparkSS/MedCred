<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DelayRule extends Model
{
    protected $fillable = ['name', 'status_id', 'event_key', 'delay_owner_id', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    public function delayOwner(): BelongsTo
    {
        return $this->belongsTo(DelayOwner::class);
    }
}
