<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class ZipLookupService
{
    public function lookup(string $zip): ?array
    {
        $zip5 = $this->normalize($zip);

        if ($zip5 === null) {
            return null;
        }

        $cacheKey = "zip-lookup:us:{$zip5}";

        if (Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey);

            return $cached === false ? null : $cached;
        }

        $result = $this->fetch($zip5);

        if ($result === 'error') {
            return null;
        }

        Cache::put($cacheKey, $result ?? false, now()->addDay());

        return $result;
    }

    private function normalize(string $zip): ?string
    {
        $digits = preg_replace('/\D/', '', $zip) ?? '';

        return strlen($digits) >= 5 ? substr($digits, 0, 5) : null;
    }

    private function fetch(string $zip5): array|string|null
    {
        try {
            $response = Http::timeout(8)->acceptJson()->get("https://api.zippopotam.us/us/{$zip5}");
        } catch (Throwable) {
            return 'error';
        }

        if ($response->status() === 404) {
            return null;
        }

        if (! $response->successful()) {
            return 'error';
        }

        $place = $response->json('places.0');
        if (! is_array($place)) {
            return null;
        }

        $lat = $place['latitude'] ?? null;
        $lng = $place['longitude'] ?? null;

        return [
            'city' => $place['place name'] ?? null,
            'state' => $place['state abbreviation'] ?? null,
            'county' => $this->lookupCounty($lat, $lng),
            'latitude' => $lat,
            'longitude' => $lng,
        ];
    }

    private function lookupCounty(mixed $lat, mixed $lng): ?string
    {
        if ($lat === null || $lat === '' || $lng === null || $lng === '') {
            return null;
        }

        try {
            $fcc = Http::timeout(8)->acceptJson()->get('https://geo.fcc.gov/api/census/area', [
                'lat' => $lat,
                'lon' => $lng,
                'format' => 'json',
            ]);
        } catch (Throwable) {
            return null;
        }

        if (! $fcc->successful()) {
            return null;
        }

        return $fcc->json('County.name')
            ?? $fcc->json('results.0.county_name')
            ?? $fcc->json('county_name');
    }
}
