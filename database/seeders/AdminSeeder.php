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

        if (! $superadmin->hasRole(AdminRole::SystemAdmin->value)) {
            $superadmin->assignRole(AdminRole::SystemAdmin->value);
        }
        $superadmin->syncRoles([AdminRole::SystemAdmin->value]);

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

        if (! $manager->hasRole(AdminRole::CredentialingManager->value)) {
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

        if (! $executive->hasRole(AdminRole::CredentialingExecutive->value)) {
            $executive->assignRole(AdminRole::CredentialingExecutive->value);
        }

        $billing = Admin::updateOrCreate(
            ['username' => 'billing'],
            [
                'name' => 'Billing Manager',
                'email' => 'billing@example.com',
                'phone' => '0000000003',
                'status' => 'active',
                'email_verified_at' => now(),
                'password' => 'password',
                'remember_token' => Str::random(10),
            ]
        );

        if (! $billing->hasRole(AdminRole::BillingReadonly->value)) {
            $billing->assignRole(AdminRole::BillingReadonly->value);
        }

        $practiceIds = \App\Models\Practice::query()->pluck('id')->all();
        if ($practiceIds !== []) {
            $manager->practices()->syncWithoutDetaching($practiceIds);
            $first = [$practiceIds[0]];
            $executive->practices()->syncWithoutDetaching($first);
            $billing->practices()->syncWithoutDetaching($first);
        }
    }
}
