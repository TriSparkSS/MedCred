<?php

namespace App\Models;

use App\Enums\PracticeStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'user_id',
    'legal_name',
    'client_code',
    'dba_name',
    'ein_tin',
    'group_npi',
    'taxonomy_code',
    'phone',
    'fax',
    'email',
    'website',
    'status',
    'license_number',
    'bank_name',
    'bank_account',
    'bank_routing_number',
    'bank_address',
    'bank_phone',
    'document_path',
    'document_original_name',
])]
class Practice extends Model
{
    protected $casts = [
        'status' => PracticeStatus::class,
    ];

    protected static function booted(): void
    {
        static::saving(function (Practice $practice) {
            if (filled($practice->client_code)) {
                $practice->client_code = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string) $practice->client_code) ?? '', 0, 3));
            }
        });

        static::deleting(function (Practice $practice) {
            $practice->addresses()->delete();
            $practice->contacts()->delete();
            $practice->locations()->delete();
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function addresses()
    {
        return $this->morphMany(Address::class, 'addressable');
    }

    public function primaryAddress()
    {
        return $this->morphOne(Address::class, 'addressable')->where('type', 'primary');
    }

    public function alternativeAddress()
    {
        return $this->morphOne(Address::class, 'addressable')->where('type', 'alternative');
    }

    public function mailingAddress()
    {
        return $this->morphOne(Address::class, 'addressable')->where('type', 'mailing');
    }

    public function billingAddress()
    {
        return $this->morphOne(Address::class, 'addressable')->where('type', 'billing');
    }

    public function providers()
    {
        return $this->belongsToMany(ProviderDetails::class, 'provider_practice', 'practice_id', 'provider_id')
            ->using(ProviderPractice::class)
            ->withPivot('id', 'primary_flag', 'start_date', 'end_date')
            ->withTimestamps();
    }

    public function contacts()
    {
        return $this->hasMany(PracticeContact::class);
    }

    public function locations()
    {
        return $this->hasMany(Location::class);
    }

    public function primaryLocation()
    {
        return $this->hasOne(Location::class)->where('is_primary', true);
    }

    public function credentialingCases()
    {
        return $this->hasMany(CredentialingCase::class, 'practice_id');
    }

    public function documents()
    {
        return $this->hasMany(Document::class, 'practice_id');
    }
}
