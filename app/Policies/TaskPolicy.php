<?php

namespace App\Policies;

use App\Enums\AdminRole;
use App\Models\Admin;
use App\Models\Task;

class TaskPolicy
{
    public function viewAny(Admin $admin): bool
    {
        return $admin->can('admin.tasks.view');
    }

    public function view(Admin $admin, Task $task): bool
    {
        return $admin->can('admin.tasks.view');
    }

    public function create(Admin $admin): bool
    {
        return $admin->can('admin.tasks.manage');
    }

    public function update(Admin $admin, Task $task): bool
    {
        return $admin->can('admin.tasks.manage');
    }

    public function delete(Admin $admin, Task $task): bool
    {
        return $admin->can('admin.tasks.manage');
    }

    public function assign(Admin $admin, Task $task): bool
    {
        return $admin->can('admin.tasks.assign');
    }

    public function escalate(Admin $admin, Task $task): bool
    {
        return $admin->can('admin.tasks.escalate');
    }

    public function reopen(Admin $admin, Task $task): bool
    {
        if (! $admin->can('admin.tasks.reopen')) {
            return false;
        }

        if ($admin->hasRole(AdminRole::CredentialingExecutive->value)) {
            return (int) $task->assigned_admin_id === (int) $admin->id;
        }

        return true;
    }
}
