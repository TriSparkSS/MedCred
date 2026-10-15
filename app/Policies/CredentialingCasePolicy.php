<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\CredentialingCase;
use App\Services\AdminScopeService;

class CredentialingCasePolicy
{
    public function viewAny(Admin $admin): bool
    {
        return $admin->can('admin.credentials.view');
    }

    public function view(Admin $admin, CredentialingCase $case): bool
    {
        return $admin->can('admin.credentials.view')
            && app(AdminScopeService::class)->canAccessCase($admin, $case);
    }

    public function create(Admin $admin): bool
    {
        return $admin->can('admin.credentials.create');
    }

    public function update(Admin $admin, CredentialingCase $case): bool
    {
        return $admin->can('admin.credentials.edit')
            && app(AdminScopeService::class)->canAccessCase($admin, $case);
    }

    public function assign(Admin $admin, CredentialingCase $case): bool
    {
        return $admin->can('admin.credentials.assign')
            && app(AdminScopeService::class)->canAccessCase($admin, $case);
    }

    public function overrideDelay(Admin $admin, CredentialingCase $case): bool
    {
        return $admin->can('admin.delay.override')
            && app(AdminScopeService::class)->canAccessCase($admin, $case);
    }
}
