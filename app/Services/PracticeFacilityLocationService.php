<?php

namespace App\Services;

use App\Models\Location;
use App\Support\UsStates;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

/**
 * Single source of truth for practice facility locations. Used by the Edit page section
 * (PracticeLocationsSection) and by Add Practice so both save identical data.
 */
class PracticeFacilityLocationService
{
    public const FIELDS = [
        'name', 'address1', 'address2', 'city', 'state', 'zip_code', 'county',
        'country', 'phone', 'fax', 'taxonomy_code', 'npi', 'status', 'is_primary',
    ];

    /**
     * Validation rules for one facility location. $prefix is e.g. 'formData' or 'facilityLocations.*'.
     */
    public static function rules(string $prefix = 'formData'): array
    {
        return [
            "{$prefix}.name" => 'required|string|max:255',
            "{$prefix}.address1" => 'required|string|max:255',
            "{$prefix}.address2" => 'nullable|string|max:255',
            "{$prefix}.city" => 'required|string|max:100',
            "{$prefix}.state" => ['required', 'string', 'size:2', Rule::in(UsStates::codes())],
            "{$prefix}.zip_code" => 'required|string|max:20',
            "{$prefix}.county" => 'nullable|string|max:100',
            "{$prefix}.country" => 'required|string|max:100',
            "{$prefix}.phone" => 'nullable|string|max:30',
            "{$prefix}.fax" => 'nullable|string|max:30',
            "{$prefix}.taxonomy_code" => 'nullable|string|max:50',
            "{$prefix}.npi" => 'nullable|string|max:50',
            "{$prefix}.status" => 'required|in:active,inactive',
            "{$prefix}.is_primary" => 'boolean',
        ];
    }

    public static function emptyRow(): array
    {
        return [
            'name' => '',
            'address1' => '',
            'address2' => '',
            'city' => '',
            'state' => '',
            'zip_code' => '',
            'county' => '',
            'country' => 'United States',
            'phone' => '',
            'fax' => '',
            'taxonomy_code' => '',
            'npi' => '',
            'status' => 'active',
            'is_primary' => false,
        ];
    }

    public function create(int $practiceId, array $data): Location
    {
        $payload = $this->payload($data);

        if ($payload['is_primary'] ?? false) {
            $this->clearPrimary($practiceId);
        }

        return Location::create([...$payload, 'practice_id' => $practiceId]);
    }

    public function update(Location $location, array $data): Location
    {
        $payload = $this->payload($data);

        if ($payload['is_primary'] ?? false) {
            $this->clearPrimary((int) $location->practice_id);
        }

        $location->update($payload);

        return $location->fresh();
    }

    protected function clearPrimary(int $practiceId): void
    {
        Location::where('practice_id', $practiceId)->update(['is_primary' => false]);
    }

    protected function payload(array $data): array
    {
        $payload = Arr::only($data, self::FIELDS);

        if (array_key_exists('state', $payload)) {
            $payload['state'] = UsStates::normalize($payload['state'] ?? null) ?? $payload['state'];
        }

        if (array_key_exists('is_primary', $payload)) {
            $payload['is_primary'] = (bool) $payload['is_primary'];
        }

        return $payload;
    }
}
