<?php

namespace Database\Seeders;

use App\Enums\AdminRole;
use App\Models\Admin;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AdminPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'admin.dashboard.view',
            'admin.providers.view',
            'admin.providers.create',
            'admin.providers.edit',
            'admin.providers.delete',
            'admin.practices.view',
            'admin.practices.manage',
            'admin.practices.delete',
            'admin.credentials.view',
            'admin.credentials.create',
            'admin.credentials.edit',
            'admin.credentials.assign',
            'admin.documents.view',
            'admin.documents.upload',
            'admin.documents.verify',
            'admin.emails.view',
            'admin.emails.send',
            'admin.emails.link',
            'admin.tasks.view',
            'admin.tasks.manage',
            'admin.tasks.assign',
            'admin.tasks.escalate',
            'admin.tasks.reopen',
            'admin.tasks.delete',
            'admin.reports.view',
            'admin.reports.export',
            'admin.analytics.view',
            'admin.delay.override',
            'admin.billing.view',
            'admin.billing.notify',
            'admin.settings.manage',
            'admin.settings.mail',
            'admin.portal-credentials.manage',
            'admin.users.manage',
            'admin.imports.manage',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'admin']);
        }

        $managerExcluded = [
            'admin.settings.manage',
            'admin.users.manage',
            'admin.portal-credentials.manage',
            'admin.settings.mail',
        ];

        $roles = [
            AdminRole::SystemAdmin->value => $permissions,
            AdminRole::CredentialingManager->value => array_values(array_diff($permissions, $managerExcluded)),
            AdminRole::CredentialingExecutive->value => [
                'admin.dashboard.view',
                'admin.providers.view',
                'admin.practices.view',
                'admin.credentials.view',
                'admin.credentials.create',
                'admin.credentials.edit',
                'admin.documents.view',
                'admin.documents.upload',
                'admin.documents.verify',
                'admin.emails.view',
                'admin.emails.send',
                'admin.emails.link',
                'admin.tasks.view',
                'admin.tasks.manage',
                'admin.tasks.reopen',
            ],
            AdminRole::BillingReadonly->value => [
                'admin.dashboard.view',
                'admin.providers.view',
                'admin.practices.view',
                'admin.credentials.view',
                'admin.billing.view',
                'admin.reports.view',
                'admin.analytics.view',
            ],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'admin']);
            $role->syncPermissions($rolePermissions);
        }

        $superadmin = Admin::where('username', 'superadmin')->first();
        if ($superadmin && ! $superadmin->hasRole(AdminRole::SystemAdmin->value)) {
            $superadmin->assignRole(AdminRole::SystemAdmin->value);
        }
    }
}
