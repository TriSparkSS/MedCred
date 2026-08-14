@props(['documentTypes', 'submissionChannels', 'applicationTypes', 'submitLabel' => 'Save Payer'])

<div class="row g-4 mb-4">
    <div class="col-md-4">
        <label class="form-label fw-medium">Payer Name <span class="text-danger">*</span></label>
        <input type="text" wire:model="formData.name" class="form-control @error('formData.name') is-invalid @enderror" placeholder="e.g. Aetna, BCBS">
        @error('formData.name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label fw-medium">States Applicable</label>
        <input type="text" wire:model="formData.states_applicable" class="form-control @error('formData.states_applicable') is-invalid @enderror" placeholder="CA, NY, TX or All">
        @error('formData.states_applicable')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label fw-medium">Application Type</label>
        <select wire:model="formData.application_type" class="form-select @error('formData.application_type') is-invalid @enderror">
            <option value="">Select type...</option>
            @foreach ($applicationTypes as $type)
                <option value="{{ $type }}">{{ ucfirst($type) }}</option>
            @endforeach
        </select>
        @error('formData.application_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label fw-medium">Submission Channel</label>
        <select wire:model="formData.submission_channel" class="form-select @error('formData.submission_channel') is-invalid @enderror">
            <option value="">Select channel...</option>
            @foreach ($submissionChannels as $channel)
                <option value="{{ $channel }}">{{ ucfirst($channel) }}</option>
            @endforeach
        </select>
        @error('formData.submission_channel')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label fw-medium">Turnaround (days)</label>
        <input type="number" wire:model="formData.turnaround_days" class="form-control @error('formData.turnaround_days') is-invalid @enderror" min="1" placeholder="Expected days">
        @error('formData.turnaround_days')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label fw-medium">Status</label>
        <select wire:model="formData.is_active" class="form-select">
            <option value="1">Active</option>
            <option value="0">Inactive</option>
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label fw-medium">Email</label>
        <input type="email" wire:model="formData.email" class="form-control @error('formData.email') is-invalid @enderror" placeholder="payer@example.com">
        @error('formData.email')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label fw-medium">Phone</label>
        <input type="text" wire:model="formData.phone" class="form-control @error('formData.phone') is-invalid @enderror">
        @error('formData.phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label fw-medium">Fax</label>
        <input type="text" wire:model="formData.fax" class="form-control @error('formData.fax') is-invalid @enderror">
        @error('formData.fax')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label fw-medium">Portal URL</label>
        <input type="url" wire:model="formData.portal_url" class="form-control @error('formData.portal_url') is-invalid @enderror" placeholder="https://portal.payer.com">
        @error('formData.portal_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label fw-medium">Portal Notes</label>
        <input type="text" wire:model="formData.portal_notes" class="form-control @error('formData.portal_notes') is-invalid @enderror" placeholder="Login instructions or notes">
        @error('formData.portal_notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-12">
        <label class="form-label fw-medium">Participation Rules</label>
        <textarea wire:model="formData.participation_rules" rows="3" class="form-control @error('formData.participation_rules') is-invalid @enderror" placeholder="Panel rules, eligibility notes..."></textarea>
        @error('formData.participation_rules')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>

<div class="mb-3 pb-3 border-bottom">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h6 class="text-primary fw-semibold mb-1"><i class="ti tabler-file-check me-2"></i>Document Requirements</h6>
            <p class="text-muted small mb-0">Required documents by payer and state.</p>
        </div>
        <button type="button" wire:click="addDocumentRequirement" class="btn btn-sm btn-outline-primary">
            <i class="ti tabler-plus me-1"></i>Add Requirement
        </button>
    </div>
</div>

@foreach ($documentRequirements as $index => $requirement)
    <div class="row g-3 mb-3 align-items-end" wire:key="doc-req-{{ $index }}">
        <div class="col-md-5">
            <label class="form-label fw-medium">Document Type</label>
            <select wire:model="documentRequirements.{{ $index }}.document_type_id" class="form-select">
                <option value="">Select document...</option>
                @foreach ($documentTypes as $docType)
                    <option value="{{ $docType->id }}">{{ $docType->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label fw-medium">State (optional)</label>
            <input type="text" wire:model="documentRequirements.{{ $index }}.state" class="form-control" placeholder="CA">
        </div>
        <div class="col-md-2">
            <div class="form-check form-switch mt-4">
                <input type="checkbox" class="form-check-input" wire:model="documentRequirements.{{ $index }}.is_required" id="req-{{ $index }}">
                <label class="form-check-label" for="req-{{ $index }}">Required</label>
            </div>
        </div>
        <div class="col-md-2">
            @if (count($documentRequirements) > 1)
                <button type="button" wire:click="removeDocumentRequirement({{ $index }})" class="btn btn-outline-danger w-100">
                    <i class="ti tabler-trash"></i>
                </button>
            @endif
        </div>
    </div>
@endforeach
