<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DelayOwnerHistory extends Model
{
    protected $fillable = [
        'credentialing_case_id',
        'delay_owner_id',
        'previous_delay_owner_id',
        'source',
        'reason',
        'changed_by_admin_id',
    ];

    public function credentialingCase(): BelongsTo
    {
        return $this->belongsTo(CredentialingCase::class);
    }

    public function delayOwner(): BelongsTo
    {
        return $this->belongsTo(DelayOwner::class);
    }

    public function previousDelayOwner(): BelongsTo
    {
        return $this->belongsTo(DelayOwner::class, 'previous_delay_owner_id');
    }

    public function changedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'changed_by_admin_id');
    }
}
