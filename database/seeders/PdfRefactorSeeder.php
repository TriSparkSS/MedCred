<?php

namespace Database\Seeders;

use App\Models\BusinessCalendar;
use App\Models\Holiday;
use App\Models\CredentialingCase;
use App\Models\Location;
use App\Models\ProviderDetails;
use App\Models\ProviderPracticeLocation;
use Illuminate\Database\Seeder;

class PdfRefactorSeeder extends Seeder
{
    public function run(): void
    {
        $calendar = BusinessCalendar::firstOrCreate(
            ['name' => 'US Federal Business Calendar'],
            ['exclude_weekends' => true, 'is_default' => true, 'is_active' => true]
        );

        $holidays = [
            ['2026-01-01', 'New Year\'s Day'],
            ['2026-07-04', 'Independence Day'],
            ['2026-12-25', 'Christmas Day'],
        ];

        foreach ($holidays as [$date, $name]) {
            Holiday::firstOrCreate(
                ['business_calendar_id' => $calendar->id, 'date' => $date],
                ['name' => $name]
            );
        }

        ProviderDetails::with('practices')->each(function (ProviderDetails $provider) {
            foreach ($provider->practices as $practice) {
                $location = Location::where('practice_id', $practice->id)
                    ->orderByDesc('is_primary')
                    ->first();

                ProviderPracticeLocation::firstOrCreate(
                    [
                        'provider_id' => $provider->id,
                        'practice_id' => $practice->id,
                        'location_id' => $location?->id,
                    ],
                    [
                        'start_date' => $practice->pivot->start_date ?? now()->toDateString(),
                        'end_date' => $practice->pivot->end_date,
                        'role' => 'rendering',
                        'is_primary' => (bool) ($practice->pivot->primary_flag ?? false),
                    ]
                );
            }
        });

        CredentialingCase::whereNull('provider_practice_location_id')
            ->orderBy('id')
            ->each(function (CredentialingCase $case) {
                $enrollment = ProviderPracticeLocation::where('provider_id', $case->provider_id)
                    ->where('practice_id', $case->practice_id)
                    ->when($case->location_id, fn ($q) => $q->where('location_id', $case->location_id))
                    ->first();

                if ($enrollment) {
                    $case->update(['provider_practice_location_id' => $enrollment->id]);
                }
            });
    }
}
