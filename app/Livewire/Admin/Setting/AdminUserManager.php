<?php

namespace App\Livewire\Admin\Setting;

use App\Enums\AdminRole;
use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\Practice;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::admin', ['title' => 'Admin Users | Settings'])]
class AdminUserManager extends Component
{
    use WithPagination;

    public $search = '';

    public $showRoleModal = false;

    public $showUserModal = false;

    public $showPracticeModal = false;

    #[Locked]
    public $editingUserId = null;

    #[Locked]
    public $adminId = null;

    #[Locked]
    public $practiceAdminId = null;

    public $selectedRoles = [];

    /** @var list<int|string> */
    public array $selectedPracticeIds = [];

    public $userName = '';

    public $userUsername = '';

    public $userEmail = '';

    public $userPassword = '';

    public $userStatus = 'active';

    public $userRole = '';

    public bool $is_two_factor = false;

    public bool $userTwoFactor = false;

    public function render()
    {
        $records = Admin::with('roles', 'practices')
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

        $allPractices = Practice::orderBy('legal_name')->get(['id', 'legal_name', 'client_code']);

        return view('livewire.admin.setting.admin-user-manager', compact('records', 'availableRoles', 'allPractices'));
    }

    public function updated($propertyName): void
    {
        if ($propertyName === 'search') {
            $this->resetPage();
        } elseif ($propertyName === 'userTwoFactor') {
            $this->is_two_factor = (bool) $this->userTwoFactor;
        } elseif ($propertyName === 'is_two_factor') {
            $this->userTwoFactor = (bool) $this->is_two_factor;
        }
    }

    public function openCreateModal(): void
    {
        abort_unless(Auth::guard('admin')->user()?->can('admin.users.manage'), 403);
        $this->resetUserForm();
        $this->editingUserId = null;
        $this->showUserModal = true;
    }

    public function openEditModal(int $id): void
    {
        abort_unless(Auth::guard('admin')->user()?->can('admin.users.manage'), 403);
        $admin = Admin::findOrFail($id);
        $this->editingUserId = $id;
        $this->userName = $admin->name;
        $this->userUsername = $admin->username;
        $this->userEmail = $admin->email;
        $this->userPassword = '';
        $this->userStatus = $admin->status ?? 'active';
        $this->userRole = '';
        $this->is_two_factor = (bool) $admin->is_two_factor;
        $this->userTwoFactor = (bool) $admin->is_two_factor;
        $this->showUserModal = true;
    }

    public function openRoleModal(int $id): void
    {
        abort_unless(Auth::guard('admin')->user()?->can('admin.users.manage'), 403);
        $admin = Admin::with('roles')->findOrFail($id);
        $this->adminId = $id;
        $this->selectedRoles = $admin->roles->pluck('name')->toArray();
        $this->showRoleModal = true;
    }

    public function openPracticeModal(int $id): void
    {
        abort_unless(Auth::guard('admin')->user()?->can('admin.users.manage'), 403);
        $admin = Admin::with('practices')->findOrFail($id);
        $this->practiceAdminId = $id;
        $this->selectedPracticeIds = $admin->practices->pluck('id')->map(fn ($id) => (string) $id)->all();
        $this->showPracticeModal = true;
    }

    public function savePractices(): void
    {
        abort_unless(Auth::guard('admin')->user()?->can('admin.users.manage'), 403);
        $this->validate([
            'selectedPracticeIds' => 'array',
            'selectedPracticeIds.*' => 'integer|exists:practices,id',
        ]);

        $admin = Admin::findOrFail($this->practiceAdminId);
        $practiceIds = array_map('intval', $this->selectedPracticeIds);
        $admin->practices()->sync($practiceIds);

        AuditLog::record('admin.practices_updated', $admin, Auth::guard('admin')->id(), [
            'practice_ids' => $practiceIds,
        ]);

        flash()->success('Practice assignments updated for ' . $admin->name . '.');
        $this->closePracticeModal();
    }

    public function closePracticeModal(): void
    {
        $this->showPracticeModal = false;
        $this->practiceAdminId = null;
        $this->selectedPracticeIds = [];
        $this->resetValidation();
    }

    protected function userFormRules(): array
    {
        $roleValues = implode(',', array_map(fn (AdminRole $r) => $r->value, AdminRole::cases()));
        $passwordRule = Password::min(8)
            ->mixedCase()
            ->numbers()
            ->symbols();

        $rules = [
            'userName' => 'required|string|max:255',
            'userEmail' => [
                'required',
                'email',
                'max:255',
                Rule::unique('admins', 'email')->ignore($this->editingUserId),
            ],
            'userStatus' => 'required|in:active,inactive',
            'is_two_factor' => 'boolean',
        ];

        if ($this->editingUserId) {
            $rules['userPassword'] = ['nullable', 'string', $passwordRule];
        } else {
            $rules['userUsername'] = 'required|string|max:255|unique:admins,username';
            $rules['userPassword'] = ['required', 'string', $passwordRule];
            $rules['userRole'] = 'required|string|in:' . $roleValues;
        }

        return $rules;
    }

    protected function roleRules(): array
    {
        return [
            'selectedRoles' => 'required|array|min:1',
            'selectedRoles.*' => 'string|in:' . implode(',', array_map(fn (AdminRole $r) => $r->value, AdminRole::cases())),
        ];
    }

    public function saveUser(): void
    {
        abort_unless(Auth::guard('admin')->user()?->can('admin.users.manage'), 403);
        $this->validate($this->userFormRules());

        if ($this->editingUserId) {
            $admin = Admin::findOrFail($this->editingUserId);
            $admin->update([
                'name' => $this->userName,
                'email' => $this->userEmail,
                'status' => $this->userStatus,
                'is_two_factor' => (bool) $this->is_two_factor,
            ]);

            if ($this->userPassword !== '') {
                $admin->update([
                    'password' => $this->userPassword,
                    'password_changed_at' => now(),
                ]);
            }

            AuditLog::record('admin.user_updated', $admin, Auth::guard('admin')->id(), [
                'status' => $this->userStatus,
                'is_two_factor' => (bool) $this->is_two_factor,
                'password_changed' => $this->userPassword !== '',
            ]);

            flash()->success('Admin user updated successfully.');
        } else {
            $admin = Admin::create([
                'name' => $this->userName,
                'username' => $this->userUsername,
                'email' => $this->userEmail,
                'password' => $this->userPassword,
                'password_changed_at' => now(),
                'status' => $this->userStatus,
                'is_two_factor' => (bool) $this->is_two_factor,
            ]);

            $admin->assignRole($this->userRole);

            AuditLog::record('admin.user_created', $admin, Auth::guard('admin')->id(), [
                'role' => $this->userRole,
                'status' => $this->userStatus,
                'is_two_factor' => (bool) $this->is_two_factor,
            ]);

            flash()->success('Admin user created successfully.');
        }

        $this->closeUserModal();
    }

    public function saveRoles(): void
    {
        abort_unless(Auth::guard('admin')->user()?->can('admin.users.manage'), 403);
        $this->validate($this->roleRules());

        $admin = Admin::findOrFail($this->adminId);
        $admin->syncRoles($this->selectedRoles);

        AuditLog::record('admin.roles_updated', $admin, Auth::guard('admin')->id(), [
            'roles' => array_values($this->selectedRoles),
        ]);

        flash()->success('Roles updated successfully for ' . $admin->name . '.');
        $this->closeRoleModal();
    }

    public function unlockAdmin(int $id): void
    {
        abort_unless(Auth::guard('admin')->user()?->can('admin.users.manage'), 403);

        $user = Admin::findOrFail($id);
        $user->update([
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ]);

        AuditLog::record('admin.unlocked', $user, Auth::guard('admin')->id());

        flash()->success('Admin account unlocked for ' . $user->name . '.');
    }

    public function toggleStatus(int $id): void
    {
        abort_unless(Auth::guard('admin')->user()?->can('admin.users.manage'), 403);

        $user = Admin::findOrFail($id);
        $newStatus = ($user->status ?? 'active') === 'active' ? 'inactive' : 'active';
        $user->update(['status' => $newStatus]);

        AuditLog::record('admin.status_toggled', $user, Auth::guard('admin')->id(), [
            'status' => $newStatus,
        ]);

        flash()->success('Status updated for ' . $user->name . '.');
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
        $this->is_two_factor = false;
        $this->userTwoFactor = false;
    }
}
