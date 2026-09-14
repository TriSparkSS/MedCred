<?php

namespace App\Livewire\Concerns;

use App\Services\ZipLookupService;

trait LooksUpUsZip
{
    public array $zipLookupMessages = [];

    public function lookupZip(string $property, string $zipKey = 'zip_code'): void
    {
        $data = $this->{$property};
        $zip = is_array($data) ? ($data[$zipKey] ?? null) : null;
        $digits = preg_replace('/\D/', '', (string) $zip) ?? '';

        if (strlen($digits) < 5) {
            unset($this->zipLookupMessages[$property]);

            return;
        }

        $result = app(ZipLookupService::class)->lookup((string) $zip);

        if ($result === null) {
            $this->zipLookupMessages[$property] = 'Zip code not found. Enter city, state, and county manually.';

            return;
        }

        unset($this->zipLookupMessages[$property]);

        if (! empty($result['city'])) {
            $data['city'] = $result['city'];
        }

        if (! empty($result['state'])) {
            $data['state'] = $result['state'];
        }

        if (array_key_exists('county', $data) && ! empty($result['county'])) {
            $data['county'] = $result['county'];
        }

        $this->{$property} = $data;
    }
}
