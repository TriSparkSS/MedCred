<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseSlaTimer extends Model
{
    protected $fillable = [
        'credentialing_case_id',
        'rule_key',
        'started_at',
        'due_at',
        'triggered_at',
        'completed_at',
        'status',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'due_at' => 'datetime',
        'triggered_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function credentialingCase(): BelongsTo
    {
        return $this->belongsTo(CredentialingCase::class);
    }

    public function isDue(): bool
    {
        return $this->status === 'active' && $this->due_at->lte(now());
    }

    public function markTriggered(): void
    {
        $this->update([
            'status' => 'triggered',
            'triggered_at' => now(),
        ]);
    }

    public function markCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }
}
