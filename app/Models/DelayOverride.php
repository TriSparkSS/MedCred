<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DelayOverride extends Model
{
    protected $fillable = [
        'credentialing_case_id',
        'previous_delay_owner_id',
        'new_delay_owner_id',
        'reason',
        'admin_id',
    ];

    public function credentialingCase(): BelongsTo
    {
        return $this->belongsTo(CredentialingCase::class);
    }

    public function previousDelayOwner(): BelongsTo
    {
        return $this->belongsTo(DelayOwner::class, 'previous_delay_owner_id');
    }

    public function newDelayOwner(): BelongsTo
    {
        return $this->belongsTo(DelayOwner::class, 'new_delay_owner_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }
}
