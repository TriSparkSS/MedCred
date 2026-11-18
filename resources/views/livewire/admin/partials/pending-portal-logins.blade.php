{{-- Add Practice / Add Provider: portal logins saved (password encrypted) with the new record. Param: $heading --}}
<div class="mb-4" wire:key="pending-portal-logins">
    <div class="mb-3 pb-3 border-bottom d-flex justify-content-between align-items-center gap-3">
        <div>
            <h6 class="text-primary fw-semibold mb-1">
                <i class="ti tabler-key me-2"></i>{{ $heading }}
            </h6>
            <p class="text-muted small mb-0">Payer and portal credentials. Passwords are stored encrypted.</p>
        </div>
        <button type="button" wire:click="addPortalLoginRow" class="btn btn-sm btn-outline-primary text-nowrap flex-shrink-0">
            <i class="ti tabler-plus me-1"></i>Add Login
        </button>
    </div>

    @forelse ($portalLogins as $index => $login)
        <div class="border rounded p-3 mb-3" wire:key="pending-portal-login-{{ $index }}">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="fw-semibold small text-muted"><i class="ti tabler-lock me-1"></i>Login #{{ $index + 1 }}</span>
                <button type="button" wire:click="removePortalLoginRow({{ $index }})"
                    class="btn btn-sm btn-icon btn-outline-danger" title="Remove">
                    <i class="ti tabler-trash"></i>
                </button>
            </div>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-medium">Portal Name <span class="text-danger">*</span></label>
                    <input type="text" wire:model="portalLogins.{{ $index }}.portal_name"
                        class="form-control @error('portalLogins.'.$index.'.portal_name') is-invalid @enderror"
                        placeholder="e.g. Availity">
                    @error('portalLogins.'.$index.'.portal_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-medium">ID / Assigned Number</label>
                    <input type="text" wire:model="portalLogins.{{ $index }}.assigned_id"
                        class="form-control @error('portalLogins.'.$index.'.assigned_id') is-invalid @enderror">
                    @error('portalLogins.'.$index.'.assigned_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-medium">User Name</label>
                    <input type="text" wire:model="portalLogins.{{ $index }}.username" autocomplete="off"
                        class="form-control @error('portalLogins.'.$index.'.username') is-invalid @enderror">
                    @error('portalLogins.'.$index.'.username')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-medium">Password <span class="text-danger">*</span></label>
                    <input type="password" wire:model="portalLogins.{{ $index }}.password" autocomplete="new-password"
                        class="form-control @error('portalLogins.'.$index.'.password') is-invalid @enderror">
                    @error('portalLogins.'.$index.'.password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-medium">MFA Email</label>
                    <input type="email" wire:model="portalLogins.{{ $index }}.mfa_email"
                        class="form-control @error('portalLogins.'.$index.'.mfa_email') is-invalid @enderror">
                    @error('portalLogins.'.$index.'.mfa_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-medium">MFA Phone</label>
                    <input type="text" wire:model="portalLogins.{{ $index }}.mfa_phone"
                        class="form-control @error('portalLogins.'.$index.'.mfa_phone') is-invalid @enderror">
                    @error('portalLogins.'.$index.'.mfa_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label class="form-label fw-medium">Portal URL</label>
                    <input type="text" wire:model="portalLogins.{{ $index }}.portal_url"
                        class="form-control @error('portalLogins.'.$index.'.portal_url') is-invalid @enderror"
                        placeholder="https://">
                    @error('portalLogins.'.$index.'.portal_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-medium">Security questions</label>
                    <textarea wire:model="portalLogins.{{ $index }}.security_notes" rows="2"
                        class="form-control @error('portalLogins.'.$index.'.security_notes') is-invalid @enderror"></textarea>
                    @error('portalLogins.'.$index.'.security_notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-medium">Account numbers and notes</label>
                    <textarea wire:model="portalLogins.{{ $index }}.account_details" rows="2"
                        class="form-control @error('portalLogins.'.$index.'.account_details') is-invalid @enderror"></textarea>
                    @error('portalLogins.'.$index.'.account_details')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
    @empty
        <p class="text-muted small text-center py-2 mb-0">No portal logins added yet.</p>
    @endforelse
</div>
