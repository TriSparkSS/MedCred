<?php

namespace Database\Seeders;

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

        Admin::updateOrCreate(
            [
                'username' => 'superadmin',
                'email' => 'admin@hrm.com',
            ],
            [
                'name' => 'Super Admin',
                'avatar' => $avatar,
                'phone' => '0000000000',
                'email_verified_at' => now(),
                'password' => Hash::make('112233'),
                'remember_token' => Str::random(10),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
