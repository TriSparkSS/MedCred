<?php

namespace App\Models;

use App\Enums\PracticeStatus;
use App\Support\UsStates;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
    'license_name',
    'license_effective_date',
    'license_expiry_date',
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
    public const CLIENT_CODE_MIN = 3;

    public const CLIENT_CODE_MAX = 12;

    public const CLIENT_CODE_PATTERN = '/^[A-Z0-9]{3,12}$/';

    protected $casts = [
        'status' => PracticeStatus::class,
        'license_effective_date' => 'date',
        'license_expiry_date' => 'date',
    ];

    public static function normalizeClientCode(?string $value): ?string
    {
        $normalized = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $value) ?? '');

        if ($normalized === '') {
            return null;
        }

        return substr($normalized, 0, self::CLIENT_CODE_MAX);
    }

    public static function isValidClientCode(?string $value): bool
    {
        return (bool) preg_match(self::CLIENT_CODE_PATTERN, (string) $value);
    }

    public static function clientCodeValidationRule(): string
    {
        return 'required|string|min:'.self::CLIENT_CODE_MIN.'|max:'.self::CLIENT_CODE_MAX.'|regex:/^[A-Za-z0-9]{'.self::CLIENT_CODE_MIN.','.self::CLIENT_CODE_MAX.'}$/';
    }

    protected static function booted(): void
    {
        static::saving(function (Practice $practice) {
            if (filled($practice->client_code)) {
                $practice->client_code = self::normalizeClientCode((string) $practice->client_code);
            }
            foreach (['license_effective_date', 'license_expiry_date'] as $field) {
                if ($practice->{$field} === '') {
                    $practice->{$field} = null;
                }
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

    public function portalLogins()
    {
        return $this->morphMany(PortalLogin::class, 'loginable');
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

    /**
     * @return array<string, array{label: string, practice: string, address: string, city: string, state: string, zip: string}>
     */
    public function selectableAddressOptions(): array
    {
        $options = [];
        $typeOrder = ['mailing', 'primary', 'alternative', 'billing'];
        $typeLabels = [
            'primary' => 'Primary',
            'alternative' => 'Secondary',
            'mailing' => 'Mailing',
            'billing' => 'Billing',
        ];

        $addresses = $this->addresses
            ->sortBy(fn (Address $address) => array_search($address->type, $typeOrder, true) === false
                ? 99
                : array_search($address->type, $typeOrder, true));

        foreach ($addresses as $address) {
            $street = trim(($address->address1 ?? '').($address->address2 ? ', '.$address->address2 : ''));
            if ($street === '' && ! filled($address->city) && ! filled($address->zip_code)) {
                continue;
            }

            $typeLabel = $typeLabels[$address->type] ?? ucfirst((string) $address->type);
            $name = filled($address->location_name) ? ' — '.$address->location_name : '';
            $cityState = trim(($address->city ?? '').', '.($address->state ?? ''), ' ,');

            $options['address:'.$address->id] = [
                'label' => $typeLabel.' Address'.$name.($street !== '' ? ' — '.$street : '').($cityState !== '' ? ', '.$cityState : ''),
                'practice' => $this->legal_name,
                'address' => $street,
                'city' => (string) ($address->city ?? ''),
                'state' => UsStates::normalize($address->state) ?? (string) ($address->state ?? ''),
                'zip' => (string) ($address->zip_code ?? ''),
            ];
        }

        $locations = $this->locations->sortByDesc('is_primary')->values();
        foreach ($locations as $location) {
            $street = trim(($location->address1 ?? '').($location->address2 ? ', '.$location->address2 : ''));
            $kind = $location->is_primary ? 'Primary Service Location' : 'Service Location';
            $cityState = trim(($location->city ?? '').', '.($location->state ?? ''), ' ,');

            $options['location:'.$location->id] = [
                'label' => $kind.' — '.$location->name.($street !== '' ? ' — '.$street : '').($cityState !== '' ? ', '.$cityState : ''),
                'practice' => $this->legal_name,
                'address' => $street,
                'city' => (string) ($location->city ?? ''),
                'state' => UsStates::normalize($location->state) ?? (string) ($location->state ?? ''),
                'zip' => (string) ($location->zip_code ?? ''),
            ];
        }

        return $options;
    }

    public function credentialingCases()
    {
        return $this->hasMany(CredentialingCase::class, 'practice_id');
    }

    public function documents()
    {
        return $this->hasMany(Document::class, 'practice_id');
    }

    public function assignedAdmins(): BelongsToMany
    {
        return $this->belongsToMany(Admin::class, 'admin_practice')->withTimestamps();
    }
}
