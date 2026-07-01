<?php

namespace Database\Seeders;

use App\Enums\AdminRole;
use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $avatar = getUserImageInitial(1, 'superadmin');

        $superadmin = Admin::updateOrCreate(
            [
                'username' => 'superadmin',
                'email' => 'admin@hrm.com',
            ],
            [
                'name' => 'Super Admin',
                'avatar' => $avatar,
                'phone' => '0000000000',
                'status' => 'active',
                'email_verified_at' => now(),
                'password' => Hash::make('112233'),
                'remember_token' => Str::random(10),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        if ($superadmin->roles()->count() === 0) {
            $superadmin->assignRole(AdminRole::SystemAdmin->value);
        }

        $manager = Admin::updateOrCreate(
            ['username' => 'manager'],
            [
                'name' => 'Credentialing Manager',
                'email' => 'manager@revantage.test',
                'phone' => '0000000001',
                'status' => 'active',
                'email_verified_at' => now(),
                'password' => 'password',
                'remember_token' => Str::random(10),
            ]
        );

        if ($manager->roles()->count() === 0) {
            $manager->assignRole(AdminRole::CredentialingManager->value);
        }

        $executive = Admin::updateOrCreate(
            ['username' => 'executive'],
            [
                'name' => 'Credentialing Executive',
                'email' => 'executive@revantage.test',
                'phone' => '0000000002',
                'status' => 'active',
                'email_verified_at' => now(),
                'password' => 'password',
                'remember_token' => Str::random(10),
            ]
        );

        if ($executive->roles()->count() === 0) {
            $executive->assignRole(AdminRole::CredentialingExecutive->value);
        }
    }
}
