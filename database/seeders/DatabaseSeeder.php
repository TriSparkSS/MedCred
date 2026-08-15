<?php

namespace Database\Seeders;

use App\Models\User;
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
        // User::factory(10)->create();

        $this->call([
            RoleSeeder::class,
            PermissionSeeder::class,
            AdminPermissionSeeder::class,
            AdminSeeder::class,
            MasterDataSeeder::class,
            PdfRefactorSeeder::class,
        ]);

        // Optional demo data: php artisan db:seed --class=DummyContentSeeder
    }
}
