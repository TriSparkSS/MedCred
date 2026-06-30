<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use App\Enums\ProviderStatus;

#[Fillable(['user_id', 'specialty_id', 'status', 'npi', 'caqh_id', 'license_number', 'license_state', 'dea', 'taxonomy_code', 'pecos_id', 'pecos_enrolled', 'malpractice_carrier', 'malpractice_policy_number', 'malpractice_expiry', 'board_certification', 'board_cert_expiry', 'cds_number', 'cds_state', 'work_history', 'licensed_states', 'practice', 'address', 'city', 'state', 'zip'])]
class ProviderDetails extends Model
{

    protected $casts = [
        'status' => ProviderStatus::class,
        'pecos_enrolled' => 'boolean',
        'malpractice_expiry' => 'date',
        'board_cert_expiry' => 'date',
        'licensed_states' => 'array',
    ];

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

    public function credentialingCases()
    {
        return $this->hasMany(CredentialingCase::class, 'provider_id');
    }

    public function documents()
    {
        return $this->hasMany(Document::class, 'provider_id');
    }

    public function tasks()
    {
        return $this->hasMany(Task::class, 'provider_id');
    }
}
