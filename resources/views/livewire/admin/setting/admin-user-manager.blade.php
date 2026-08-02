<div class="container-fluid px-3 px-md-4 py-4">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <div class="row align-items-center g-3">
                <div class="col-lg-3">
                    <h4 class="mb-1 fw-bold text-primary">Admin Users</h4>
                    <small class="text-muted">Manage admin accounts and role assignments</small>
                </div>
                <div class="col-lg-6">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="ti tabler-search"></i></span>
                        <input type="search" wire:model.live="search" class="form-control border-start-0"
                            placeholder="Search by name, email, or username...">
                    </div>
                </div>
                <div class="col-lg-3 text-lg-end">
                    <button wire:click="openCreateModal" class="btn btn-primary">
                        <i class="ti tabler-user-plus me-1"></i> Create Admin User
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Roles</th>
                        <th width="160" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($records as $record)
                        <tr>
                            <td class="fw-semibold">{{ $record->name }}</td>
                            <td>{{ $record->username }}</td>
                            <td>{{ $record->email }}</td>
                            <td>
                                <span
                                    class="badge {{ ($record->status ?? 'active') === 'active' ? 'bg-label-success' : 'bg-label-secondary' }}">
                                    {{ ucfirst($record->status ?? 'active') }}
                                </span>
                            </td>
                            <td>
                                @forelse($record->roles as $role)
                                    <span class="badge bg-label-primary me-1">{{ $role->name }}</span>
                                @empty
                                    <span class="text-muted">No roles assigned</span>
                                @endforelse
                            </td>
                            <td class="text-end">
                                <div class="d-flex justify-content-end gap-1">
                                    <button wire:click="openEditModal({{ $record->id }})"
                                        class="btn btn-sm btn-outline-secondary" title="Edit">
                                        <i class="ti tabler-edit"></i>
                                    </button>
                                    <button wire:click="openRoleModal({{ $record->id }})"
                                        class="btn btn-sm btn-outline-primary">
                                        <i class="ti tabler-shield me-1"></i>Roles
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">No admin users found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white">{{ $records->links('livewire::bootstrap') }}</div>
    </div>

    @if ($showUserModal)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,.5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ $editingUserId ? 'Edit Admin User' : 'Create Admin User' }}</h5>
                        <button type="button" class="btn-close" wire:click="closeUserModal"></button>
                    </div>
                    <form wire:submit.prevent="saveUser">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Name <span class="text-danger">*</span></label>
                                <input type="text" wire:model="userName"
                                    class="form-control @error('userName') is-invalid @enderror">
                                @error('userName')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            @if (!$editingUserId)
                                <div class="mb-3">
                                    <label class="form-label">Username <span class="text-danger">*</span></label>
                                    <input type="text" wire:model="userUsername"
                                        class="form-control @error('userUsername') is-invalid @enderror">
                                    @error('userUsername')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            @else
                                <div class="mb-3">
                                    <label class="form-label">Username</label>
                                    <input type="text" class="form-control" value="{{ $userUsername }}" disabled>
                                </div>
                            @endif
                            <div class="mb-3">
                                <label class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" wire:model="userEmail"
                                    class="form-control @error('userEmail') is-invalid @enderror">
                                @error('userEmail')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label">
                                    Password @if (!$editingUserId)
                                    <span class="text-danger">*</span>@else<small class="text-muted">(leave blank to
                                            keep current)</small>
                                    @endif
                                </label>
                                <input type="password" wire:model="userPassword"
                                    class="form-control @error('userPassword') is-invalid @enderror"
                                    autocomplete="new-password">
                                @error('userPassword')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <select wire:model="userStatus"
                                    class="form-select @error('userStatus') is-invalid @enderror">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                                @error('userStatus')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            @if (!$editingUserId)
                                <div class="mb-0">
                                    <label class="form-label">Role <span class="text-danger">*</span></label>
                                    <select wire:model="userRole"
                                        class="form-select @error('userRole') is-invalid @enderror">
                                        <option value="">Select role...</option>
                                        @foreach ($availableRoles as $role)
                                            <option value="{{ $role['value'] }}">{{ $role['label'] }}</option>
                                        @endforeach
                                    </select>
                                    @error('userRole')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            @endif
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary"
                                wire:click="closeUserModal">Cancel</button>
                            <button type="submit"
                                class="btn btn-primary">{{ $editingUserId ? 'Save Changes' : 'Create User' }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @if ($showRoleModal)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,.5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Assign Roles</h5>
                        <button type="button" class="btn-close" wire:click="closeRoleModal"></button>
                    </div>
                    <form wire:submit.prevent="saveRoles">
                        <div class="modal-body">
                            <p class="text-muted small mb-3">Select one or more roles for this admin user.</p>
                            <div class="d-flex flex-column gap-2">
                                @foreach ($availableRoles as $role)
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input"
                                            id="admin_role_{{ $role['value'] }}" value="{{ $role['value'] }}"
                                            wire:model="selectedRoles">
                                        <label class="form-check-label"
                                            for="admin_role_{{ $role['value'] }}">{{ $role['label'] }}</label>
                                    </div>
                                @endforeach
                            </div>
                            @error('selectedRoles')
                                <div class="text-danger small mt-2">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary"
                                wire:click="closeRoleModal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save Roles</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
