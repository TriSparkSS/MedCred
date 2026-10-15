<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            PermissionSeeder::class,
            AdminPermissionSeeder::class,
            AdminSeeder::class,
            MasterDataSeeder::class,
            SpecialtySeeder::class,
            PdfRefactorSeeder::class,
            Phase2Seeder::class,
        ]);

        // Optional demo data: php artisan db:seed --class=DummyContentSeeder
    }
}
