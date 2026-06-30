<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payer extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'states_applicable',
        'application_type',
        'submission_channel',
        'portal_url',
        'portal_notes',
        'fax',
        'email',
        'phone',
        'turnaround_days',
        'participation_rules',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'turnaround_days' => 'integer',
    ];

    public function documentRequirements(): HasMany
    {
        return $this->hasMany(PayerDocumentRequirement::class);
    }

    public function credentialingCases(): HasMany
    {
        return $this->hasMany(CredentialingCase::class, 'payer_id');
    }
}
