<div class="container-fluid flex-grow-1 px-3 px-md-4 py-3 py-md-4">
    @php use App\Enums\ProviderStatus; @endphp

    {{-- Page Header --}}
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-start gap-3">
            <div>
                <h4 class="fw-bold text-primary mb-1">
                    <i class="ti tabler-edit me-2"></i>Edit Provider
                </h4>
                <p class="text-muted mb-0">Update provider profile, contact information, and credentialing details.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('admin.providers.show', $providerId) }}" class="btn btn-outline-primary">
                    <i class="ti tabler-eye me-1"></i>View Profile
                </a>
                <a href="{{ route('admin.providers') }}" class="btn btn-outline-secondary">
                    <i class="ti tabler-arrow-left me-1"></i>Back to list
                </a>
            </div>
        </div>
    </div>

    {{-- Form --}}
    <div class="card shadow-sm border-0">
        <div class="card-body p-4">
            <form wire:submit.prevent="save">

                {{-- Account Information --}}
                <div class="mb-3 pb-3 border-bottom">
                    <h6 class="text-primary fw-semibold mb-1">
                        <i class="ti tabler-user me-2"></i>Account Information
                    </h6>
                    <p class="text-muted small mb-0">Contact details linked to the provider login account.</p>
                </div>
                <div class="row g-4 mb-4">
                    <div class="col-md-4">
                        <label class="form-label fw-medium">Full Name <span class="text-danger">*</span></label>
                        <input type="text" wire:model="userData.name"
                            class="form-control @error('userData.name') is-invalid @enderror"
                            placeholder="Dr. Jane Smith">
                        @error('userData.name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-medium">Email Address <span class="text-danger">*</span></label>
                        <input type="email" wire:model="userData.email"
                            class="form-control @error('userData.email') is-invalid @enderror"
                            placeholder="provider@example.com">
                        @error('userData.email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-medium">Phone Number</label>
                        <input type="text" wire:model="userData.phone"
                            class="form-control @error('userData.phone') is-invalid @enderror"
                            placeholder="(555) 123-4567">
                        @error('userData.phone')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                {{-- Professional Details --}}
                <div class="mb-3 pb-3 border-bottom">
                    <h6 class="text-primary fw-semibold mb-1">
                        <i class="ti tabler-stethoscope me-2"></i>Professional Details
                    </h6>
                    <p class="text-muted small mb-0">NPI, specialty, and credentialing status.</p>
                </div>
                <div class="row g-4 mb-4">
                    <div class="col-md-4">
                        <label class="form-label fw-medium">Specialty</label>
                        <select wire:model="formData.specialty_id"
                            class="form-select @error('formData.specialty_id') is-invalid @enderror">
                            <option value="">Select specialty...</option>
                            @foreach ($specialties as $specialty)
                                <option value="{{ $specialty->id }}">{{ $specialty->name }}</option>
                            @endforeach
                        </select>
                        @error('formData.specialty_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-medium">NPI Number <span class="text-danger">*</span></label>
                        <input type="text" wire:model="formData.npi"
                            class="form-control @error('formData.npi') is-invalid @enderror"
                            placeholder="10-digit NPI">
                        @error('formData.npi')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-medium">Status <span class="text-danger">*</span></label>
                        <select wire:model="formData.status"
                            class="form-select @error('formData.status') is-invalid @enderror">
                            <option value="">Select status...</option>
                            <option value="{{ ProviderStatus::PENDING->value }}">Pending</option>
                            <option value="{{ ProviderStatus::APPROVED->value }}">Approved</option>
                            <option value="{{ ProviderStatus::REJECTED->value }}">Rejected</option>
                        </select>
                        @error('formData.status')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                {{-- Licenses & Credentials --}}
                <div class="mb-3 pb-3 border-bottom">
                    <h6 class="text-primary fw-semibold mb-1">
                        <i class="ti tabler-certificate me-2"></i>Licenses & Credentials
                    </h6>
                    <p class="text-muted small mb-0">CAQH profile, state license, and DEA registration.</p>
                </div>
                <div class="row g-4 mb-4">
                    <div class="col-md-4">
                        <label class="form-label fw-medium">CAQH ID</label>
                        <input type="text" wire:model="formData.caqh_id"
                            class="form-control @error('formData.caqh_id') is-invalid @enderror"
                            placeholder="Numeric CAQH Provider ID"
                            inputmode="numeric"
                            pattern="[0-9]*">
                        @error('formData.caqh_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-medium">License Number</label>
                        <input type="text" wire:model="formData.license_number"
                            class="form-control @error('formData.license_number') is-invalid @enderror"
                            placeholder="State medical license number">
                        @error('formData.license_number')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-medium">License State</label>
                        <input type="text" wire:model="formData.license_state"
                            class="form-control @error('formData.license_state') is-invalid @enderror"
                            placeholder="e.g. CA, NY, TX">
                        @error('formData.license_state')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-medium">DEA Number</label>
                        <input type="text" wire:model="formData.dea"
                            class="form-control @error('formData.dea') is-invalid @enderror"
                            placeholder="Alphanumeric DEA registration"
                            maxlength="20">
                        @error('formData.dea')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                {{-- Practice & Location --}}
                <div class="mb-3 pb-3 border-bottom">
                    <h6 class="text-primary fw-semibold mb-1">
                        <i class="ti tabler-map-pin me-2"></i>Practice & Location
                    </h6>
                    <p class="text-muted small mb-0">Primary practice affiliation and mailing address.</p>
                </div>
                <div class="row g-4 mb-4">
                    <div class="col-12">
                        <label class="form-label fw-medium">Practice Name <span class="text-danger">*</span></label>
                        <input type="text" wire:model="formData.practice"
                            class="form-control @error('formData.practice') is-invalid @enderror"
                            placeholder="Associated practice or clinic name">
                        @error('formData.practice')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-medium">Street Address <span class="text-danger">*</span></label>
                        <input type="text" wire:model="formData.address"
                            class="form-control @error('formData.address') is-invalid @enderror"
                            placeholder="123 Medical Center Drive, Suite 100">
                        @error('formData.address')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-medium">City <span class="text-danger">*</span></label>
                        <input type="text" wire:model="formData.city"
                            class="form-control @error('formData.city') is-invalid @enderror"
                            placeholder="City">
                        @error('formData.city')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-medium">State <span class="text-danger">*</span></label>
                        <input type="text" wire:model="formData.state"
                            class="form-control @error('formData.state') is-invalid @enderror"
                            placeholder="State">
                        @error('formData.state')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-medium">Zip Code <span class="text-danger">*</span></label>
                        <input type="text" wire:model="formData.zip"
                            class="form-control @error('formData.zip') is-invalid @enderror"
                            placeholder="Zip code">
                        @error('formData.zip')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                {{-- Actions --}}
                <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 pt-3 border-top">
                    <p class="text-muted small mb-0">
                        <span class="text-danger">*</span> Required fields
                    </p>
                    <div class="d-flex gap-2">
                        <a href="{{ route('admin.providers') }}" class="btn btn-outline-secondary">
                            Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="ti tabler-device-floppy me-1"></i>Save Changes
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
