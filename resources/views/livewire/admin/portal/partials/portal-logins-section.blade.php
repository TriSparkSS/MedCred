<div>
<div class="card shadow-sm border-0">
    <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <h5 class="mb-0">{{ $heading }}</h5>
            <small class="text-muted">External portal credentials (CAQH, payer sites, PECOS). Not the CRM portal login.</small>
        </div>
        @if ($canManage)
            <button type="button" wire:click.stop="openCreateModal" class="btn btn-sm btn-primary">
                <i class="ti tabler-plus me-1"></i>Add Login
            </button>
        @endif
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Portal Name</th>
                    <th>User Name</th>
                    <th>MFA Email</th>
                    <th>MFA Phone</th>
                    @if ($canManage)
                        <th class="text-end">Actions</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse($logins as $login)
                    <tr wire:key="portal-login-{{ $login->id }}">
                        <td class="fw-semibold">{{ $login->portal_name }}</td>
                        <td>{{ $login->username ?: '—' }}</td>
                        <td><small>{{ $login->mfa_email ?: '—' }}</small></td>
                        <td><small>{{ $login->mfa_phone ?: '—' }}</small></td>
                        @if ($canManage)
                            <td class="text-end">
                                <button type="button" wire:click.stop="openEditModal({{ $login->id }})" class="btn btn-sm btn-outline-primary">
                                    <i class="ti tabler-edit me-1"></i>Edit
                                </button>
                                <button type="button" wire:click.stop="delete({{ $login->id }})" wire:confirm="Remove this portal login?" class="btn btn-sm btn-outline-danger">
                                    <i class="ti tabler-trash"></i>
                                </button>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $canManage ? 5 : 4 }}" class="text-center text-muted py-4">No portal logins on file.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if ($showModal)
    <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,.5); z-index: 2000; position: fixed; inset: 0;">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ $modalMode === 'create' ? 'Add Portal Login' : 'Edit Portal Login' }}</h5>
                    <button type="button" class="btn-close" wire:click="closeModal"></button>
                </div>
                <form wire:submit.prevent="save">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Portal Name <span class="text-danger">*</span></label>
                                <input type="text" wire:model="formData.portal_name" class="form-control @error('formData.portal_name') is-invalid @enderror" placeholder="e.g. CAQH, Availity, PECOS">
                                @error('formData.portal_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">User Name</label>
                                <input type="text" wire:model="formData.username" class="form-control @error('formData.username') is-invalid @enderror">
                                @error('formData.username')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Password @if ($modalMode !== 'edit')<span class="text-danger">*</span>@endif</label>
                                <input type="password" wire:model="formData.password" class="form-control @error('formData.password') is-invalid @enderror" autocomplete="new-password">
                                @error('formData.password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                @if ($modalMode === 'edit')
                                    <div class="form-text">Leave blank to keep the current password.</div>
                                @endif
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">MFA email</label>
                                <input type="email" wire:model="formData.mfa_email" class="form-control @error('formData.mfa_email') is-invalid @enderror" placeholder="Email associated with MFA">
                                @error('formData.mfa_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">MFA phone</label>
                                <input type="text" wire:model="formData.mfa_phone" class="form-control @error('formData.mfa_phone') is-invalid @enderror" placeholder="Phone associated with MFA">
                                @error('formData.mfa_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="mt-4 pt-3 border-top">
                            <h6 class="fw-semibold mb-1">Security and Account Details</h6>
                            <p class="text-muted small mb-3">Recovery questions, security contacts, and other account notes.</p>
                            <div class="mb-3">
                                <label class="form-label">Security notes</label>
                                <textarea wire:model="formData.security_notes" rows="2" class="form-control" placeholder="Security questions, backup codes location, etc."></textarea>
                            </div>
                            <div>
                                <label class="form-label">Account details</label>
                                <textarea wire:model="formData.account_details" rows="2" class="form-control" placeholder="Account ID, organization, billing contact, etc."></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" wire:click="closeModal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif
</div>
