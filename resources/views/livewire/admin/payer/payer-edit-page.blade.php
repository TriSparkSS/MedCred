<div class="container-fluid flex-grow-1 px-3 px-md-4 py-3 py-md-4">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-start gap-3">
            <div>
                <h4 class="fw-bold text-primary mb-1"><i class="ti tabler-edit me-2"></i>Edit Payer</h4>
                <p class="text-muted mb-0">Update payer configuration and document requirements.</p>
            </div>
            <a href="{{ route('admin.payers') }}" class="btn btn-outline-secondary"><i class="ti tabler-arrow-left me-1"></i>Back</a>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-4">
            <form wire:submit.prevent="save">
                @include('livewire.admin.payer.partials.payer-form-fields', compact('documentTypes', 'submissionChannels', 'applicationTypes'))
                <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                    <a href="{{ route('admin.payers') }}" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary"><i class="ti tabler-device-floppy me-1"></i>Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
