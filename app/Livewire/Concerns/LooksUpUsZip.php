<?php

namespace App\Livewire\Concerns;

use App\Services\ZipLookupService;

trait LooksUpUsZip
{
    public array $zipLookupMessages = [];

    /**
     * Fill city / state (and county when present) from a US ZIP code.
     *
     * $property is an array property on the component (e.g. 'formData').
     * The key arguments let one array hold several addresses, e.g. the
     * provider home address: lookupZip('formData', 'home_zip', 'home_city', 'home_state').
     * Repeater rows use a dotted path to the row: lookupZip('facilityLocations.0').
     */
    public function lookupZip(
        string $property,
        string $zipKey = 'zip_code',
        string $cityKey = 'city',
        string $stateKey = 'state',
        string $countyKey = 'county',
    ): void {
        // Keep the original message key for the default address so existing views keep working.
        $messageKey = $cityKey === 'city' ? $property : "{$property}.{$zipKey}";

        [$root, $path] = str_contains($property, '.')
            ? explode('.', $property, 2)
            : [$property, null];

        if (! property_exists($this, $root)) {
            return;
        }

        $data = $path === null ? $this->{$root} : data_get($this->{$root}, $path);
        $zip = is_array($data) ? ($data[$zipKey] ?? null) : null;
        $digits = preg_replace('/\D/', '', (string) $zip) ?? '';

        if (strlen($digits) < 5) {
            unset($this->zipLookupMessages[$messageKey]);

            return;
        }

        $result = app(ZipLookupService::class)->lookup((string) $zip);

        if ($result === null) {
            $this->zipLookupMessages[$messageKey] = array_key_exists($countyKey, (array) $data)
                ? 'Zip code not found. Enter city, state, and county manually.'
                : 'Zip code not found. Enter city and state manually.';

            return;
        }

        unset($this->zipLookupMessages[$messageKey]);

        if (! empty($result['city'])) {
            $data[$cityKey] = $result['city'];
        }

        if (! empty($result['state'])) {
            $data[$stateKey] = $result['state'];
        }

        if (array_key_exists($countyKey, $data) && ! empty($result['county'])) {
            $data[$countyKey] = $result['county'];
        }

        if ($path === null) {
            $this->{$root} = $data;

            return;
        }

        $rootData = $this->{$root};
        data_set($rootData, $path, $data);
        $this->{$root} = $rootData;
    }
}
