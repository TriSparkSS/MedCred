<?php

namespace App\Services;

use App\Models\Location;
use App\Models\ProviderDetails;
use App\Models\ProviderPractice;
use App\Models\ProviderPracticeLocation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProviderLocationService
{
    public function saveLink(ProviderDetails $provider, array $data, ?int $linkId = null): ProviderPracticeLocation
    {
        $practiceId = (int) $data['practice_id'];
        $locationId = (int) $data['location_id'];

        $location = Location::where('practice_id', $practiceId)->findOrFail($locationId);

        $duplicate = ProviderPracticeLocation::query()
            ->where('provider_id', $provider->id)
            ->where('location_id', $location->id)
            ->when($linkId, fn ($q) => $q->whereKeyNot($linkId))
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'formData.location_id' => 'This provider is already linked to that location.',
            ]);
        }

        return DB::transaction(function () use ($provider, $data, $linkId, $practiceId, $location) {
            $this->ensurePracticeAssignment($provider, $practiceId);

            $isPrimary = (bool) ($data['is_primary'] ?? false);
            if ($isPrimary) {
                ProviderPracticeLocation::query()
                    ->where('provider_id', $provider->id)
                    ->when($linkId, fn ($q) => $q->whereKeyNot($linkId))
                    ->update(['is_primary' => false]);
            }

            $payload = [
                'provider_id' => $provider->id,
                'practice_id' => $practiceId,
                'location_id' => $location->id,
                'role' => ($data['role'] ?? null) ?: null,
                'start_date' => ($data['start_date'] ?? null) ?: null,
                'end_date' => ($data['end_date'] ?? null) ?: null,
                'is_primary' => $isPrimary,
            ];

            if ($linkId) {
                $link = ProviderPracticeLocation::query()
                    ->where('provider_id', $provider->id)
                    ->findOrFail($linkId);
                $link->update($payload);
            } else {
                $link = ProviderPracticeLocation::create($payload);
            }

            if (! ProviderPracticeLocation::query()->where('provider_id', $provider->id)->where('is_primary', true)->exists()) {
                $link->update(['is_primary' => true]);
            }

            return $link->fresh(['practice', 'location']);
        });
    }

    public function deleteLink(ProviderPracticeLocation $link): void
    {
        $providerId = $link->provider_id;
        $wasPrimary = $link->is_primary;
        $link->delete();

        if ($wasPrimary) {
            $next = ProviderPracticeLocation::query()
                ->where('provider_id', $providerId)
                ->orderBy('id')
                ->first();
            $next?->update(['is_primary' => true]);
        }
    }

    public function syncForPractice(ProviderDetails $provider, int $practiceId, array $locationIds): void
    {
        $locationIds = collect($locationIds)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        DB::transaction(function () use ($provider, $practiceId, $locationIds) {
            $this->ensurePracticeAssignment($provider, $practiceId);

            $validIds = Location::query()
                ->where('practice_id', $practiceId)
                ->whereIn('id', $locationIds)
                ->pluck('id');

            ProviderPracticeLocation::query()
                ->where('provider_id', $provider->id)
                ->where('practice_id', $practiceId)
                ->whereNotIn('location_id', $validIds)
                ->delete();

            $existing = ProviderPracticeLocation::query()
                ->where('provider_id', $provider->id)
                ->where('practice_id', $practiceId)
                ->pluck('location_id')
                ->filter()
                ->map(fn ($id) => (int) $id);

            $hasPrimary = ProviderPracticeLocation::query()
                ->where('provider_id', $provider->id)
                ->where('is_primary', true)
                ->exists();

            foreach ($validIds as $locationId) {
                if ($existing->contains((int) $locationId)) {
                    continue;
                }

                ProviderPracticeLocation::create([
                    'provider_id' => $provider->id,
                    'practice_id' => $practiceId,
                    'location_id' => $locationId,
                    'is_primary' => ! $hasPrimary,
                ]);
                $hasPrimary = true;
            }
        });
    }

    public function deleteForPractice(int $providerId, int $practiceId): void
    {
        $links = ProviderPracticeLocation::query()
            ->where('provider_id', $providerId)
            ->where('practice_id', $practiceId)
            ->get();

        $hadPrimary = $links->contains(fn ($link) => $link->is_primary);
        ProviderPracticeLocation::query()
            ->where('provider_id', $providerId)
            ->where('practice_id', $practiceId)
            ->delete();

        if ($hadPrimary) {
            $next = ProviderPracticeLocation::query()
                ->where('provider_id', $providerId)
                ->orderBy('id')
                ->first();
            $next?->update(['is_primary' => true]);
        }
    }

    public function ensurePracticeAssignment(ProviderDetails $provider, int $practiceId): ProviderPractice
    {
        $existing = ProviderPractice::query()
            ->where('provider_id', $provider->id)
            ->where('practice_id', $practiceId)
            ->first();

        if ($existing) {
            return $existing;
        }

        $isPrimaryPractice = ! ProviderPractice::query()
            ->where('provider_id', $provider->id)
            ->where('primary_flag', true)
            ->exists();

        return ProviderPractice::create([
            'provider_id' => $provider->id,
            'practice_id' => $practiceId,
            'primary_flag' => $isPrimaryPractice,
        ]);
    }
}
