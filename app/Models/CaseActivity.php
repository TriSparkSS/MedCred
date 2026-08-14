<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseActivity extends Model
{
    protected $fillable = [
        'credentialing_case_id',
        'activity_type',
        'admin_id',
        'user_id',
        'summary',
        'reference_number',
        'next_action_date',
        'metadata',
    ];

    protected $casts = [
        'next_action_date' => 'date',
        'metadata' => 'array',
    ];

    public function credentialingCase(): BelongsTo
    {
        return $this->belongsTo(CredentialingCase::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
