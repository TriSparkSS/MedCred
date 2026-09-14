<?php

namespace App\Models;

use App\Enums\ProviderCredentialStatus;
use App\Enums\ProviderCredentialType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderCredential extends Model
{
    protected $fillable = [
        'provider_id',
        'credential_type',
        'state',
        'number',
        'issue_date',
        'expiry_date',
        'status',
        'is_primary',
        'document_id',
        'notes',
    ];

    protected $casts = [
        'credential_type' => ProviderCredentialType::class,
        'status' => ProviderCredentialStatus::class,
        'issue_date' => 'date',
        'expiry_date' => 'date',
        'is_primary' => 'boolean',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(ProviderDetails::class, 'provider_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function isExpired(): bool
    {
        return $this->expiry_date && $this->expiry_date->isPast();
    }

    public function isExpiringSoon(int $days = 30): bool
    {
        if (! $this->expiry_date) {
            return false;
        }

        return $this->expiry_date->isFuture()
            && $this->expiry_date->lte(now()->addDays($days));
    }

    public function displayStatus(): ProviderCredentialStatus
    {
        if ($this->isExpired()) {
            return ProviderCredentialStatus::Expired;
        }

        return $this->status ?? ProviderCredentialStatus::Active;
    }
}
