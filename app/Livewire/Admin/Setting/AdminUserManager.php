<?php

namespace App\Livewire\Admin\Setting;

use App\Enums\AdminRole;
use App\Models\Admin;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::admin', ['title' => 'Admin Users | Settings'])]
class AdminUserManager extends Component
{
    use WithPagination;

    public $search = '';

    public $showRoleModal = false;

    public $showUserModal = false;

    public $editingUserId = null;

    public $adminId = null;

    public $selectedRoles = [];

    public $userName = '';

    public $userUsername = '';

    public $userEmail = '';

    public $userPassword = '';

    public $userStatus = 'active';

    public $userRole = '';

    public function render()
    {
        $records = Admin::with('roles')
            ->when($this->search, fn ($q) => $q->where(function ($query) {
                $query->where('name', 'like', '%' . $this->search . '%')
                    ->orWhere('email', 'like', '%' . $this->search . '%')
                    ->orWhere('username', 'like', '%' . $this->search . '%');
            }))
            ->orderBy('name')
            ->paginate(15);

        $availableRoles = collect(AdminRole::cases())->map(fn (AdminRole $role) => [
            'value' => $role->value,
            'label' => $role->label(),
        ]);

        return view('livewire.admin.setting.admin-user-manager', compact('records', 'availableRoles'));
    }

    public function updated($propertyName): void
    {
        if ($propertyName === 'search') {
            $this->resetPage();
        }
    }

    public function openCreateModal(): void
    {
        $this->resetUserForm();
        $this->editingUserId = null;
        $this->showUserModal = true;
    }

    public function openEditModal(int $id): void
    {
        $admin = Admin::findOrFail($id);
        $this->editingUserId = $id;
        $this->userName = $admin->name;
        $this->userUsername = $admin->username;
        $this->userEmail = $admin->email;
        $this->userPassword = '';
        $this->userStatus = $admin->status ?? 'active';
        $this->userRole = '';
        $this->showUserModal = true;
    }

    public function openRoleModal(int $id): void
    {
        $admin = Admin::with('roles')->findOrFail($id);
        $this->adminId = $id;
        $this->selectedRoles = $admin->roles->pluck('name')->toArray();
        $this->showRoleModal = true;
    }

    protected function userFormRules(): array
    {
        $roleValues = implode(',', array_map(fn (AdminRole $r) => $r->value, AdminRole::cases()));

        $rules = [
            'userName' => 'required|string|max:255',
            'userEmail' => [
                'required',
                'email',
                'max:255',
                Rule::unique('admins', 'email')->ignore($this->editingUserId),
            ],
            'userStatus' => 'required|in:active,inactive',
        ];

        if ($this->editingUserId) {
            $rules['userPassword'] = 'nullable|string|min:8';
        } else {
            $rules['userUsername'] = 'required|string|max:255|unique:admins,username';
            $rules['userPassword'] = 'required|string|min:8';
            $rules['userRole'] = 'required|string|in:' . $roleValues;
        }

        return $rules;
    }

    protected function roleRules(): array
    {
        return [
            'selectedRoles' => 'array',
            'selectedRoles.*' => 'string|in:' . implode(',', array_map(fn (AdminRole $r) => $r->value, AdminRole::cases())),
        ];
    }

    public function saveUser(): void
    {
        $this->validate($this->userFormRules());

        if ($this->editingUserId) {
            $admin = Admin::findOrFail($this->editingUserId);
            $admin->update([
                'name' => $this->userName,
                'email' => $this->userEmail,
                'status' => $this->userStatus,
            ]);

            if ($this->userPassword !== '') {
                $admin->update(['password' => $this->userPassword]);
            }

            flash()->success('Admin user updated successfully.');
        } else {
            $admin = Admin::create([
                'name' => $this->userName,
                'username' => $this->userUsername,
                'email' => $this->userEmail,
                'password' => $this->userPassword,
                'status' => $this->userStatus,
            ]);

            $admin->assignRole($this->userRole);

            flash()->success('Admin user created successfully.');
        }

        $this->closeUserModal();
    }

    public function saveRoles(): void
    {
        $this->validate($this->roleRules());

        $admin = Admin::findOrFail($this->adminId);
        $admin->syncRoles($this->selectedRoles);

        flash()->success('Roles updated successfully for ' . $admin->name . '.');
        $this->closeRoleModal();
    }

    public function closeUserModal(): void
    {
        $this->showUserModal = false;
        $this->resetUserForm();
        $this->resetValidation();
    }

    public function closeRoleModal(): void
    {
        $this->showRoleModal = false;
        $this->adminId = null;
        $this->selectedRoles = [];
        $this->resetValidation();
    }

    protected function resetUserForm(): void
    {
        $this->editingUserId = null;
        $this->userName = '';
        $this->userUsername = '';
        $this->userEmail = '';
        $this->userPassword = '';
        $this->userStatus = 'active';
        $this->userRole = '';
    }
}
