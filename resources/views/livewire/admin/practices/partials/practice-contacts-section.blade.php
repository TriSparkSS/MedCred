<div class="mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom">
        <div>
            <h6 class="text-primary fw-semibold mb-1"><i class="ti tabler-users me-2"></i>Practice Contacts</h6>
            <p class="text-muted small mb-0">Contact persons for this practice.</p>
        </div>
        <button type="button" wire:click="openCreateModal" class="btn btn-sm btn-outline-primary"><i class="ti tabler-plus me-1"></i>Add Contact</button>
    </div>

    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th>Title</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Primary</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($contacts as $contact)
                    <tr>
                        <td class="fw-semibold">{{ $contact->name }}</td>
                        <td>{{ $contact->title ?: '—' }}</td>
                        <td>{{ $contact->email ?: '—' }}</td>
                        <td>{{ $contact->phone ?: '—' }}</td>
                        <td>@if($contact->is_primary)<span class="badge bg-success">Primary</span>@endif</td>
                        <td class="text-end">
                            <button type="button" wire:click="openEditModal({{ $contact->id }})" class="btn btn-sm btn-icon btn-outline-primary"><i class="ti tabler-edit"></i></button>
                            <button type="button" wire:click="delete({{ $contact->id }})" class="btn btn-sm btn-icon btn-outline-danger"><i class="ti tabler-trash"></i></button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-3">No contacts added yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($showModal)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,.5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ $modalMode === 'create' ? 'Add Contact' : 'Edit Contact' }}</h5>
                        <button type="button" class="btn-close" wire:click="closeModal"></button>
                    </div>
                    <form wire:submit.prevent="save">
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Name <span class="text-danger">*</span></label>
                                    <input type="text" wire:model="formData.name" class="form-control @error('formData.name') is-invalid @enderror">
                                    @error('formData.name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Title</label>
                                    <input type="text" wire:model="formData.title" class="form-control" placeholder="Office Manager">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email</label>
                                    <input type="email" wire:model="formData.email" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Phone</label>
                                    <input type="text" wire:model="formData.phone" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Fax</label>
                                    <input type="text" wire:model="formData.fax" class="form-control">
                                </div>
                                <div class="col-md-6 d-flex align-items-end">
                                    <div class="form-check form-switch">
                                        <input type="checkbox" class="form-check-input" wire:model="formData.is_primary" id="contactPrimary">
                                        <label class="form-check-label" for="contactPrimary">Primary Contact</label>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Notes</label>
                                    <textarea wire:model="formData.notes" rows="2" class="form-control"></textarea>
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
