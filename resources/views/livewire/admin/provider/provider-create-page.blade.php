<div class="container-fluid flex-grow-1 px-3 px-md-4 py-3 py-md-4">
    @php use App\Enums\ProviderStatus; @endphp

    {{-- Page Header --}}
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-start gap-3">
            <div>
                <h4 class="fw-bold text-primary mb-1">
                    <i class="ti tabler-user-plus me-2"></i>Add Provider
                </h4>
                <p class="text-muted mb-0">Create a new provider profile with login credentials and credentialing details.</p>
            </div>
            <a href="{{ route('admin.providers') }}" class="btn btn-outline-secondary">
                <i class="ti tabler-arrow-left me-1"></i>Back to list
            </a>
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
                    <p class="text-muted small mb-0">Login credentials and primary contact details.</p>
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

                    <div class="col-md-4">
                        @if (auth('admin')->user()?->can('admin.portal-credentials.manage'))
                        <label class="form-label fw-medium">Password <span class="text-danger">*</span></label>
                        <input type="password" wire:model="userData.password"
                            class="form-control @error('userData.password') is-invalid @enderror"
                            placeholder="Minimum 6 characters">
                        @error('userData.password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <small class="text-muted">Initial portal password. Reset later in Admin Settings → Portal Accounts.</small>
                        @else
                        <p class="text-muted small mb-0 mt-4">Portal login credentials are managed by a system administrator.</p>
                        @endif
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
                        <input type="search" wire:model.live.debounce.300ms="specialtySearch"
                            class="form-control form-control-sm mb-2" placeholder="Search specialties...">
                        <select wire:model="formData.specialty_id"
                            class="form-select @error('formData.specialty_id') is-invalid @enderror">
                            <option value="">Select specialty...</option>
                            @foreach ($specialtyOptions as $specialty)
                                <option value="{{ $specialty->id }}">{{ $specialty->name }}{{ $specialty->code ? ' ('.$specialty->code.')' : '' }}</option>
                            @endforeach
                        </select>
                        @error('formData.specialty_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-medium">NPI Number <span class="text-danger">*</span></label>
                        <input type="text" wire:model.live="formData.npi"
                            class="form-control @error('formData.npi') is-invalid @enderror"
                            placeholder="10-digit NPI">
                        @error('formData.npi')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        @if ($npiDuplicateWarning)
                            <div class="text-warning small mt-1">{{ $npiDuplicateWarning }}</div>
                        @endif
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-medium">Provider Status <span class="text-danger">*</span></label>
                        <select wire:model.live="formData.status"
                            class="form-select @error('formData.status') is-invalid @enderror">
                            <option value="">Select status...</option>
                            @foreach (ProviderStatus::cases() as $status)
                                <option value="{{ $status->value }}">{{ $status->label() }}</option>
                            @endforeach
                        </select>
                        @error('formData.status')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    @if (($formData['status'] ?? '') === ProviderStatus::ON_HOLD->value)
                    <div class="col-md-12">
                        <label class="form-label fw-medium">Remarks <span class="text-danger">*</span></label>
                        <textarea wire:model="formData.status_remarks" rows="3"
                            class="form-control @error('formData.status_remarks') is-invalid @enderror"
                            placeholder="Required when status is On Hold"></textarea>
                        @error('formData.status_remarks')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    @endif
                </div>

                {{-- Licenses & Credentials --}}
                <div class="mb-3 pb-3 border-bottom">
                    <h6 class="text-primary fw-semibold mb-1">
                        <i class="ti tabler-certificate me-2"></i>Licenses & Credentials
                    </h6>
                    <p class="text-muted small mb-0">CAQH profile plus editable license, DEA, and CDS rows for regular maintenance.</p>
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
                </div>

                @include('livewire.admin.provider.partials.provider-credential-rows')

                @include('livewire.admin.provider.partials.provider-extended-fields')

                {{-- Practice & Location --}}
                <div class="mb-3 pb-3 border-bottom">
                    <h6 class="text-primary fw-semibold mb-1">
                        <i class="ti tabler-map-pin me-2"></i>Practice & Mailing Address
                    </h6>
                    <p class="text-muted small mb-0">Select a practice, then pick any practice address or facility location. Fields below stay editable.</p>
                </div>
                <div class="row g-4 mb-4">
                    @include('livewire.admin.provider.partials.practice-address-fields')

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
                        <label class="form-label fw-medium">Zip Code <span class="text-danger">*</span></label>
                        <input type="text" wire:model="formData.zip"
                            wire:blur="lookupZip('formData', 'zip')"
                            class="form-control @error('formData.zip') is-invalid @enderror"
                            placeholder="Zip code" maxlength="10" inputmode="numeric">
                        <div class="form-text">Leave the field to auto-fill city and state.</div>
                        @error('formData.zip')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        @if (! empty($zipLookupMessages['formData'] ?? null))
                            <div class="form-text text-warning">{{ $zipLookupMessages['formData'] }}</div>
                        @endif
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
                        <x-admin.state-select wire:model="formData.state"
                            class="{{ $errors->has('formData.state') ? 'is-invalid' : '' }}" />
                        @error('formData.state')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
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
                            <i class="ti tabler-check me-1"></i>Create Provider
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
