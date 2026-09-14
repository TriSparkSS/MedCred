<div class="card shadow-sm border-0">
    <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <h5 class="mb-0">State Licenses &amp; Registrations</h5>
            <small class="text-muted">Record License, DEA, and CDS details separately for each state.</small>
        </div>
        @if ($canEdit)
            <button type="button" wire:click="openCreateModal" class="btn btn-sm btn-primary">
                <i class="ti tabler-plus me-1"></i>Add Credential
            </button>
        @endif
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Type</th>
                    <th>State</th>
                    <th>Number</th>
                    <th>Issued</th>
                    <th>Expires</th>
                    <th>Status</th>
                    <th>Primary</th>
                    @if ($canEdit)
                        <th class="text-end">Actions</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse($credentials as $credential)
                    @php
                        $expired = $credential->isExpired();
                        $expiring = $credential->isExpiringSoon(30);
                        $status = $credential->displayStatus();
                    @endphp
                    <tr wire:key="cred-{{ $credential->id }}">
                        <td class="fw-semibold">{{ $credential->credential_type->label() }}</td>
                        <td>
                            <span class="badge bg-label-primary">{{ $credential->state }}</span>
                            <small class="text-muted d-block">{{ $states[$credential->state] ?? '' }}</small>
                        </td>
                        <td>
                            <span class="fw-semibold">{{ $credential->number }}</span>
                            @if ($credential->document)
                                <a href="{{ route('admin.documents', ['provider' => $providerId]) }}" class="d-block small">View file</a>
                            @endif
                        </td>
                        <td><small>{{ $credential->issue_date?->format('m/d/Y') ?: '—' }}</small></td>
                        <td class="{{ $expired ? 'text-danger' : ($expiring ? 'text-warning' : '') }}">
                            <small>{{ $credential->expiry_date?->format('m/d/Y') ?: '—' }}</small>
                        </td>
                        <td>
                            <span class="badge bg-label-{{ $expired ? 'danger' : ($status->value === 'active' ? 'success' : 'secondary') }}">
                                {{ $status->label() }}
                            </span>
                            @if ($expiring && ! $expired)
                                <span class="badge bg-label-warning text-dark">Expiring</span>
                            @endif
                        </td>
                        <td>
                            @if ($credential->is_primary)
                                <span class="badge bg-label-success">Primary</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        @if ($canEdit)
                            <td class="text-end">
                                <button type="button" wire:click="openEditModal({{ $credential->id }})" class="btn btn-sm btn-outline-primary" title="Edit">
                                    <i class="ti tabler-edit"></i>
                                </button>
                                <button type="button" wire:click="delete({{ $credential->id }})" class="btn btn-sm btn-outline-danger" title="Remove">
                                    <i class="ti tabler-trash"></i>
                                </button>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $canEdit ? 8 : 7 }}" class="text-center py-5 text-muted">
                            No state credentials on file. Add a license, DEA, or CDS for each enrolled state.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if ($showModal)
    <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,.5);">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ $modalMode === 'create' ? 'Add Credential' : 'Edit Credential' }}</h5>
                    <button type="button" class="btn-close" wire:click="closeModal"></button>
                </div>
                <form wire:submit.prevent="save">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Type <span class="text-danger">*</span></label>
                                <select wire:model.live="formData.credential_type" class="form-select @error('formData.credential_type') is-invalid @enderror">
                                    @foreach ($types as $type)
                                        <option value="{{ $type->value }}">{{ $type->label() }}</option>
                                    @endforeach
                                </select>
                                @error('formData.credential_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">State <span class="text-danger">*</span></label>
                                <select wire:model="formData.state" class="form-select @error('formData.state') is-invalid @enderror">
                                    <option value="">Select state...</option>
                                    @foreach ($states as $code => $name)
                                        <option value="{{ $code }}">{{ $code }} — {{ $name }}</option>
                                    @endforeach
                                </select>
                                @error('formData.state')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label">Number <span class="text-danger">*</span></label>
                                <input type="text" wire:model="formData.number" class="form-control @error('formData.number') is-invalid @enderror" placeholder="License / DEA / CDS number">
                                @error('formData.number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Issue Date</label>
                                <input type="date" wire:model="formData.issue_date" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Expiry Date</label>
                                <input type="date" wire:model="formData.expiry_date" class="form-control">
                                @error('formData.expiry_date')<div class="text-danger small">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Status</label>
                                <select wire:model="formData.status" class="form-select">
                                    @foreach ($statuses as $status)
                                        <option value="{{ $status->value }}">{{ $status->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6 d-flex align-items-end">
                                @if (($formData['credential_type'] ?? '') === 'license')
                                    <div class="form-check form-switch mb-2">
                                        <input class="form-check-input" type="checkbox" wire:model="formData.is_primary" id="credPrimary">
                                        <label class="form-check-label" for="credPrimary">Primary license</label>
                                    </div>
                                @endif
                            </div>
                            <div class="col-12">
                                <label class="form-label">Notes</label>
                                <textarea wire:model="formData.notes" rows="2" class="form-control"></textarea>
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
