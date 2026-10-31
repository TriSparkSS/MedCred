<?php

namespace App\Models;

use App\Enums\ProviderStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['user_id', 'dob', 'ssn', 'specialty_id', 'degree', 'provider_type', 'status', 'status_remarks', 'npi', 'caqh_id', 'license_number', 'license_state', 'dea', 'taxonomy_code', 'pecos_id', 'pecos_enrolled', 'malpractice_carrier', 'malpractice_policy_number', 'malpractice_expiry', 'malpractice_coverage_each_occurrence', 'malpractice_coverage_aggregate', 'malpractice_effective_date', 'board_certification', 'board_cert_expiry', 'cds_number', 'cds_state', 'work_history', 'licensed_states', 'practice', 'address', 'city', 'state', 'zip', 'home_address', 'home_city', 'home_state', 'home_zip'])]
class ProviderDetails extends Model
{
    use SoftDeletes;

    protected $casts = [
        'dob' => 'date',
        'status' => ProviderStatus::class,
        'pecos_enrolled' => 'boolean',
        'malpractice_expiry' => 'date',
        'malpractice_coverage_each_occurrence' => 'decimal:2',
        'malpractice_coverage_aggregate' => 'decimal:2',
        'malpractice_effective_date' => 'date',
        'board_cert_expiry' => 'date',
        'licensed_states' => 'array',
    ];

    protected static function booted(): void
    {
        static::deleting(function (ProviderDetails $provider) {
            if ($provider->isForceDeleting()) {
                $provider->addresses()->delete();
                $provider->portalLogins()->delete();
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function specialty()
    {
        return $this->belongsTo(Specialty::class);
    }

    public function addresses()
    {
        return $this->morphMany(Address::class, 'addressable');
    }

    public function practices()
    {
        return $this->belongsToMany(Practice::class, 'provider_practice', 'provider_id', 'practice_id')
            ->using(ProviderPractice::class)
            ->withPivot('id', 'primary_flag', 'start_date', 'end_date')
            ->withTimestamps();
    }

    public function primaryPractice(): ?Practice
    {
        $this->loadMissing('practices');

        return $this->practices
            ->sortByDesc(fn (Practice $practice) => (int) ($practice->pivot->primary_flag ?? 0))
            ->first();
    }

    public function portalLogins()
    {
        return $this->morphMany(PortalLogin::class, 'loginable');
    }

    public function credentialingCases()
    {
        return $this->hasMany(CredentialingCase::class, 'provider_id');
    }

    public function documents()
    {
        return $this->hasMany(Document::class, 'provider_id');
    }

    public function providerPracticeLocations()
    {
        return $this->hasMany(ProviderPracticeLocation::class, 'provider_id');
    }

    public function credentials()
    {
        return $this->hasMany(ProviderCredential::class, 'provider_id');
    }

    public function tasks()
    {
        return $this->hasMany(Task::class, 'provider_id');
    }
}
