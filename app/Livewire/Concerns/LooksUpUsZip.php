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

        $data = $this->{$property};
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

        $this->{$property} = $data;
    }
}
