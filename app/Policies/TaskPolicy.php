<?php

namespace App\Policies;

use App\Enums\AdminRole;
use App\Models\Admin;
use App\Models\Task;
use App\Services\AdminScopeService;

class TaskPolicy
{
    public function viewAny(Admin $admin): bool
    {
        return $admin->can('admin.tasks.view');
    }

    public function view(Admin $admin, Task $task): bool
    {
        return $admin->can('admin.tasks.view')
            && $this->canAccessTask($admin, $task);
    }

    public function create(Admin $admin): bool
    {
        return $admin->can('admin.tasks.manage');
    }

    public function update(Admin $admin, Task $task): bool
    {
        return $admin->can('admin.tasks.manage')
            && $this->canAccessTask($admin, $task);
    }

    public function delete(Admin $admin, Task $task): bool
    {
        return $admin->can('admin.tasks.delete')
            && $this->canAccessTask($admin, $task);
    }

    public function assign(Admin $admin, Task $task): bool
    {
        return $admin->can('admin.tasks.assign')
            && $this->canAccessTask($admin, $task);
    }

    public function escalate(Admin $admin, Task $task): bool
    {
        return $admin->can('admin.tasks.escalate')
            && $this->canAccessTask($admin, $task);
    }

    public function reopen(Admin $admin, Task $task): bool
    {
        if (! $admin->can('admin.tasks.reopen') || ! $this->canAccessTask($admin, $task)) {
            return false;
        }

        if ($admin->hasRole(AdminRole::CredentialingExecutive->value)) {
            return (int) $task->assigned_admin_id === (int) $admin->id;
        }

        return true;
    }

    private function canAccessTask(Admin $admin, Task $task): bool
    {
        return app(AdminScopeService::class)->canAccessTask($admin, $task);
    }
}
