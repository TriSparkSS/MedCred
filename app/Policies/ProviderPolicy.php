<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\ProviderDetails;

class ProviderPolicy
{
    public function viewAny(Admin $admin): bool
    {
        return $admin->can('admin.providers.view');
    }

    public function view(Admin $admin, ProviderDetails $provider): bool
    {
        return $admin->can('admin.providers.view');
    }

    public function create(Admin $admin): bool
    {
        return $admin->can('admin.providers.create');
    }

    public function update(Admin $admin, ProviderDetails $provider): bool
    {
        return $admin->can('admin.providers.edit');
    }
}
