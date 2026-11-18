{{-- Add Practice: contact rows saved with the new practice (same fields as the Edit page Practice Contacts). --}}
<div class="mb-4" wire:key="pending-contacts">
    <div class="mb-3 pb-3 border-bottom d-flex justify-content-between align-items-center gap-3">
        <div>
            <h6 class="text-primary fw-semibold mb-1"><i class="ti tabler-users me-2"></i>Practice Contacts</h6>
            <p class="text-muted small mb-0">Contact persons for this practice.</p>
        </div>
        <button type="button" wire:click="addContactRow" class="btn btn-sm btn-outline-primary text-nowrap flex-shrink-0">
            <i class="ti tabler-plus me-1"></i>Add Contact
        </button>
    </div>

    @forelse ($contacts as $index => $contact)
        <div class="border rounded p-3 mb-3" wire:key="pending-contact-{{ $index }}">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="fw-semibold small text-muted"><i class="ti tabler-user me-1"></i>Contact #{{ $index + 1 }}</span>
                <button type="button" wire:click="removeContactRow({{ $index }})"
                    class="btn btn-sm btn-icon btn-outline-danger" title="Remove">
                    <i class="ti tabler-trash"></i>
                </button>
            </div>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-medium">Name <span class="text-danger">*</span></label>
                    <input type="text" wire:model="contacts.{{ $index }}.name"
                        class="form-control @error('contacts.'.$index.'.name') is-invalid @enderror">
                    @error('contacts.'.$index.'.name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-medium">Title</label>
                    <input type="text" wire:model="contacts.{{ $index }}.title" placeholder="Office Manager"
                        class="form-control @error('contacts.'.$index.'.title') is-invalid @enderror">
                    @error('contacts.'.$index.'.title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-medium">Email</label>
                    <input type="email" wire:model="contacts.{{ $index }}.email"
                        class="form-control @error('contacts.'.$index.'.email') is-invalid @enderror">
                    @error('contacts.'.$index.'.email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-medium">Phone</label>
                    <input type="text" wire:model="contacts.{{ $index }}.phone"
                        class="form-control @error('contacts.'.$index.'.phone') is-invalid @enderror">
                    @error('contacts.'.$index.'.phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-medium">Fax</label>
                    <input type="text" wire:model="contacts.{{ $index }}.fax"
                        class="form-control @error('contacts.'.$index.'.fax') is-invalid @enderror">
                    @error('contacts.'.$index.'.fax')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <div class="form-check form-switch mb-2">
                        <input type="checkbox" class="form-check-input" wire:model="contacts.{{ $index }}.is_primary" id="pendingContactPrimary{{ $index }}">
                        <label class="form-check-label" for="pendingContactPrimary{{ $index }}">Primary Contact</label>
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label fw-medium">Notes</label>
                    <textarea wire:model="contacts.{{ $index }}.notes" rows="2"
                        class="form-control @error('contacts.'.$index.'.notes') is-invalid @enderror"></textarea>
                    @error('contacts.'.$index.'.notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
    @empty
        <p class="text-muted small text-center py-2 mb-0">No contacts added yet.</p>
    @endforelse
</div>
