<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'portal.dashboard.view' => 'View portal dashboard',
            'portal.cases.view' => 'View credentialing application status',
            'portal.documents.view' => 'View document requests and uploads',
            'portal.documents.upload' => 'Upload requested documents',
            'portal.profile.view' => 'View own profile information',
            'portal.providers.view' => 'View linked providers (practice)',
            'portal.locations.view' => 'View practice locations',
            'portal.action_items.view' => 'View outstanding action items',
            'billing.readiness.view' => 'Read-only provider readiness (billing)',
        ];

        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
            );
        }

        $providerPermissions = [
            'portal.dashboard.view',
            'portal.cases.view',
            'portal.documents.view',
            'portal.documents.upload',
            'portal.profile.view',
            'portal.action_items.view',
        ];

        $practicePermissions = [
            'portal.dashboard.view',
            'portal.cases.view',
            'portal.documents.view',
            'portal.documents.upload',
            'portal.profile.view',
            'portal.action_items.view',
            'portal.providers.view',
            'portal.locations.view',
        ];

        $billingPermissions = [
            'billing.readiness.view',
        ];

        Role::findByName('provider', 'web')->syncPermissions($providerPermissions);
        Role::findByName('practice', 'web')->syncPermissions($practicePermissions);
        Role::findByName('billing', 'web')->syncPermissions($billingPermissions);
    }
}
