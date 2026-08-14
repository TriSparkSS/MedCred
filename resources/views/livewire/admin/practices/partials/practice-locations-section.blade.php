<div class="mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom">
        <div>
            <h6 class="text-primary fw-semibold mb-1"><i class="ti tabler-map-pin me-2"></i>Facility Locations</h6>
            <p class="text-muted small mb-0">Practice locations and facilities for credentialing.</p>
        </div>
        <button type="button" wire:click="openCreateModal" class="btn btn-sm btn-outline-primary"><i class="ti tabler-plus me-1"></i>Add Location</button>
    </div>

    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>Location</th>
                    <th>Address</th>
                    <th>NPI</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($locations as $location)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $location->name }}</div>
                            @if($location->is_primary)<span class="badge bg-success">Primary</span>@endif
                        </td>
                        <td><small>{{ $location->address1 }}, {{ $location->city }}, {{ $location->state }} {{ $location->zip_code }}</small></td>
                        <td><small>{{ $location->npi ?: '—' }}</small></td>
                        <td><span class="badge bg-label-{{ $location->status === 'active' ? 'success' : 'secondary' }}">{{ ucfirst($location->status) }}</span></td>
                        <td class="text-end">
                            <button type="button" wire:click="openEditModal({{ $location->id }})" class="btn btn-sm btn-icon btn-outline-primary"><i class="ti tabler-edit"></i></button>
                            <button type="button" wire:click="delete({{ $location->id }})" class="btn btn-sm btn-icon btn-outline-danger"><i class="ti tabler-trash"></i></button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-3">No locations added yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($showModal)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,.5);">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ $modalMode === 'create' ? 'Add Location' : 'Edit Location' }}</h5>
                        <button type="button" class="btn-close" wire:click="closeModal"></button>
                    </div>
                    <form wire:submit.prevent="save">
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Location Name <span class="text-danger">*</span></label>
                                    <input type="text" wire:model="formData.name" class="form-control @error('formData.name') is-invalid @enderror">
                                    @error('formData.name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">NPI</label>
                                    <input type="text" wire:model="formData.npi" class="form-control">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Taxonomy</label>
                                    <input type="text" wire:model="formData.taxonomy_code" class="form-control">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Address 1 <span class="text-danger">*</span></label>
                                    <input type="text" wire:model="formData.address1" class="form-control @error('formData.address1') is-invalid @enderror">
                                    @error('formData.address1')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Address 2</label>
                                    <input type="text" wire:model="formData.address2" class="form-control">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">City <span class="text-danger">*</span></label>
                                    <input type="text" wire:model="formData.city" class="form-control @error('formData.city') is-invalid @enderror">
                                    @error('formData.city')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">State <span class="text-danger">*</span></label>
                                    <input type="text" wire:model="formData.state" class="form-control @error('formData.state') is-invalid @enderror">
                                    @error('formData.state')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Zip <span class="text-danger">*</span></label>
                                    <input type="text" wire:model="formData.zip_code" class="form-control @error('formData.zip_code') is-invalid @enderror">
                                    @error('formData.zip_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">County</label>
                                    <input type="text" wire:model="formData.county" class="form-control">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Phone</label>
                                    <input type="text" wire:model="formData.phone" class="form-control">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Status</label>
                                    <select wire:model="formData.status" class="form-select">
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </div>
                                <div class="col-md-4 d-flex align-items-end">
                                    <div class="form-check form-switch">
                                        <input type="checkbox" class="form-check-input" wire:model="formData.is_primary" id="locationPrimary">
                                        <label class="form-check-label" for="locationPrimary">Primary Location</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" wire:click="closeModal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
