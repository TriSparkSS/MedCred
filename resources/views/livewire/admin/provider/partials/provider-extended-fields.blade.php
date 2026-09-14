{{-- Extended credentialing fields for provider create/edit --}}
<div class="row g-4 mb-4">
    <div class="col-md-4">
        <label class="form-label fw-medium">Taxonomy Code</label>
        <input type="text" wire:model="formData.taxonomy_code" class="form-control @error('formData.taxonomy_code') is-invalid @enderror" placeholder="Healthcare taxonomy">
        @error('formData.taxonomy_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label fw-medium">PECOS ID</label>
        <input type="text" wire:model="formData.pecos_id" class="form-control @error('formData.pecos_id') is-invalid @enderror" placeholder="PECOS enrollment ID">
        @error('formData.pecos_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4 d-flex align-items-end">
        <div class="mb-2">
            <div class="form-check form-switch">
                <input type="checkbox" class="form-check-input" wire:model="formData.pecos_enrolled" id="pecosEnrolled">
                <label class="form-check-label" for="pecosEnrolled">PECOS Enrolled</label>
            </div>
            <small class="text-muted d-block mt-1">Indicates PECOS enrollment for Medicare; stored for reference only; does not auto-approve applications.</small>
        </div>
    </div>
</div>

<div class="mb-3 pb-3 border-bottom">
    <h6 class="text-primary fw-semibold mb-1"><i class="ti tabler-shield-check me-2"></i>Malpractice & Board Certification</h6>
    <p class="text-muted small mb-0">Insurance coverage and board certification details.</p>
</div>
<div class="row g-4 mb-4">
    <div class="col-md-4">
        <label class="form-label fw-medium">Malpractice Carrier</label>
        <input type="text" wire:model="formData.malpractice_carrier" class="form-control" placeholder="Insurance carrier name">
    </div>
    <div class="col-md-4">
        <label class="form-label fw-medium">Policy Number</label>
        <input type="text" wire:model="formData.malpractice_policy_number" class="form-control" placeholder="Policy number">
    </div>
    <div class="col-md-4">
        <label class="form-label fw-medium">Each Occurrence</label>
        <div class="input-group">
            <span class="input-group-text">$</span>
            <input type="number" wire:model="formData.malpractice_coverage_each_occurrence"
                class="form-control @error('formData.malpractice_coverage_each_occurrence') is-invalid @enderror"
                placeholder="1000000" min="0" step="0.01">
        </div>
        @error('formData.malpractice_coverage_each_occurrence')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label fw-medium">General Aggregate</label>
        <div class="input-group">
            <span class="input-group-text">$</span>
            <input type="number" wire:model="formData.malpractice_coverage_aggregate"
                class="form-control @error('formData.malpractice_coverage_aggregate') is-invalid @enderror"
                placeholder="3000000" min="0" step="0.01">
        </div>
        @error('formData.malpractice_coverage_aggregate')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label fw-medium">Policy Effective Date</label>
        <input type="date" wire:model="formData.malpractice_effective_date"
            class="form-control @error('formData.malpractice_effective_date') is-invalid @enderror">
        @error('formData.malpractice_effective_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label fw-medium">Malpractice Expiry</label>
        <input type="date" wire:model="formData.malpractice_expiry" class="form-control">
    </div>
    <div class="col-md-4">
        <label class="form-label fw-medium">Board Certification</label>
        <input type="text" wire:model="formData.board_certification" class="form-control" placeholder="Board name / certification">
    </div>
    <div class="col-md-4">
        <label class="form-label fw-medium">Board Cert Expiry</label>
        <input type="date" wire:model="formData.board_cert_expiry" class="form-control">
    </div>
    <div class="col-12">
        <label class="form-label fw-medium">Work History</label>
        <textarea wire:model="formData.work_history" rows="3" class="form-control" placeholder="Prior employment, hospitals, and practice history..."></textarea>
    </div>
</div>
