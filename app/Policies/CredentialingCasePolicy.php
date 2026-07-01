<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\CredentialingCase;

class CredentialingCasePolicy
{
    public function viewAny(Admin $admin): bool
    {
        return $admin->can('admin.credentials.view');
    }

    public function view(Admin $admin, CredentialingCase $case): bool
    {
        return $admin->can('admin.credentials.view');
    }

    public function create(Admin $admin): bool
    {
        return $admin->can('admin.credentials.create');
    }

    public function update(Admin $admin, CredentialingCase $case): bool
    {
        return $admin->can('admin.credentials.edit');
    }

    public function assign(Admin $admin, CredentialingCase $case): bool
    {
        return $admin->can('admin.credentials.assign');
    }

    public function overrideDelay(Admin $admin, CredentialingCase $case): bool
    {
        return $admin->can('admin.delay.override');
    }
}
