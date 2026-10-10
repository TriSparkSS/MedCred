<?php

namespace Database\Seeders;

use App\Models\Specialty;
use Illuminate\Database\Seeder;

class SpecialtySeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('data/specialties.txt');

        if (! is_readable($path)) {
            $this->command?->error("Specialty list not found at {$path}");

            return;
        }

        $names = collect(file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES))
            ->map(fn (string $line) => trim($line))
            ->filter()
            ->unique(fn (string $name) => mb_strtolower($name))
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        foreach ($names as $name) {
            Specialty::firstOrCreate(
                ['name' => $name],
                ['description' => null]
            );
        }
    }
}
