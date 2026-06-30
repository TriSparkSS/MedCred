<div class="container-fluid flex-grow-1 px-3 px-md-4 py-3 py-md-4">
    @php use App\Enums\PracticeStatus; @endphp

    {{-- Page Header --}}
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-start gap-3">
            <div>
                <h4 class="fw-bold text-primary mb-1">
                    <i class="ti tabler-edit me-2"></i>Edit Practice
                </h4>
                <p class="text-muted mb-0">Update practice profile, locations, bank details, and documents.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('admin.practices.show', $practiceId) }}" class="btn btn-outline-primary">
                    <i class="ti tabler-eye me-1"></i>View Profile
                </a>
                <a href="{{ route('admin.practices') }}" class="btn btn-outline-secondary">
                    <i class="ti tabler-arrow-left me-1"></i>Back to list
                </a>
            </div>
        </div>
    </div>

    {{-- Form --}}
    <div class="card shadow-sm border-0">
        <div class="card-body p-4">
            <form wire:submit.prevent="save">

                {{-- Practice Details --}}
                <div class="mb-3 pb-3 border-bottom">
                    <h6 class="text-primary fw-semibold mb-1">
                        <i class="ti tabler-building me-2"></i>Practice Details
                    </h6>
                    <p class="text-muted small mb-0">Business identifiers and contact information.</p>
                </div>
                <div class="row g-4 mb-4">
                    <div class="col-md-4">
                        <label class="form-label fw-medium">Legal Name <span class="text-danger">*</span></label>
                        <input type="text" wire:model="formData.legal_name"
                            class="form-control @error('formData.legal_name') is-invalid @enderror"
                            placeholder="Legal business name">
                        @error('formData.legal_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-medium">DBA Name</label>
                        <input type="text" wire:model="formData.dba_name"
                            class="form-control @error('formData.dba_name') is-invalid @enderror"
                            placeholder="Doing business as">
                        @error('formData.dba_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-medium">EIN/TIN</label>
                        <input type="text" wire:model="formData.ein_tin"
                            class="form-control @error('formData.ein_tin') is-invalid @enderror"
                            placeholder="Tax identification number">
                        @error('formData.ein_tin')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-medium">Group NPI</label>
                        <input type="text" wire:model="formData.group_npi"
                            class="form-control @error('formData.group_npi') is-invalid @enderror"
                            placeholder="10-digit group NPI">
                        @error('formData.group_npi')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-medium">Taxonomy Code</label>
                        <input type="text" wire:model="formData.taxonomy_code"
                            class="form-control @error('formData.taxonomy_code') is-invalid @enderror"
                            placeholder="Healthcare taxonomy code">
                        @error('formData.taxonomy_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-medium">Email <span class="text-danger">*</span></label>
                        <input type="email" wire:model="formData.email"
                            class="form-control @error('formData.email') is-invalid @enderror"
                            placeholder="practice@example.com">
                        @error('formData.email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-medium">Phone</label>
                        <input type="text" wire:model="formData.phone"
                            class="form-control @error('formData.phone') is-invalid @enderror"
                            placeholder="(555) 123-4567">
                        @error('formData.phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-medium">Fax</label>
                        <input type="text" wire:model="formData.fax"
                            class="form-control @error('formData.fax') is-invalid @enderror"
                            placeholder="Fax number">
                        @error('formData.fax')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-medium">Website</label>
                        <input type="url" wire:model="formData.website"
                            class="form-control @error('formData.website') is-invalid @enderror"
                            placeholder="https://example.com">
                        @error('formData.website')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-medium">Status <span class="text-danger">*</span></label>
                        <select wire:model="formData.status"
                            class="form-select @error('formData.status') is-invalid @enderror">
                            <option value="{{ PracticeStatus::PENDING->value }}">Pending</option>
                            <option value="{{ PracticeStatus::ACTIVE->value }}">Active</option>
                            <option value="{{ PracticeStatus::INACTIVE->value }}">Inactive</option>
                        </select>
                        @error('formData.status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                {{-- Portal Account --}}
                <div class="mb-3 pb-3 border-bottom">
                    <h6 class="text-primary fw-semibold mb-1">
                        <i class="ti tabler-lock me-2"></i>Portal Account
                    </h6>
                    <p class="text-muted small mb-0">Credentialing portal login for this practice.</p>
                </div>
                <div class="row g-4 mb-4">
                    <div class="col-md-4">
                        <label class="form-label fw-medium">Portal Login Email</label>
                        <input type="email" class="form-control bg-light" value="{{ $formData['email'] ?? '' }}" readonly>
                        <small class="text-muted">Same as practice email above. Used to sign in at the portal.</small>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-medium">New Password</label>
                        <input type="password" wire:model="userData.password"
                            class="form-control @error('userData.password') is-invalid @enderror"
                            placeholder="Leave blank to keep current">
                        @error('userData.password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <small class="text-muted">Leave blank to keep the current password. Minimum 6 characters.</small>
                    </div>
                    <div class="col-md-4 d-flex align-items-center">
                        <small class="text-muted mb-0">Portal login URL: <code>{{ url('/portal/login') }}</code></small>
                    </div>
                </div>

                {{-- Primary Location --}}
                <div class="mb-3 pb-3 border-bottom">
                    <h6 class="text-primary fw-semibold mb-1">
                        <i class="ti tabler-map-pin me-2"></i>Primary Location Address
                    </h6>
                    <p class="text-muted small mb-0">Main practice location used for credentialing and correspondence.</p>
                </div>
                <div class="mb-4">
                    @include('livewire.admin.practices.partials.address-fields', [
                        'prefix' => 'addressData',
                        'required' => true,
                        'showStatus' => true,
                    ])
                </div>

                {{-- Alternative Address --}}
                <div class="mb-3 pb-3 border-bottom">
                    <h6 class="text-primary fw-semibold mb-1">
                        <i class="ti tabler-map-2 me-2"></i>Alternative Address (Location)
                    </h6>
                    <p class="text-muted small mb-0">Optional secondary or satellite office location.</p>
                </div>
                <div class="mb-4">
                    @include('livewire.admin.practices.partials.address-fields', [
                        'prefix' => 'alternativeAddressData',
                        'required' => false,
                    ])
                </div>

                {{-- Mailing Address --}}
                <div class="mb-3 pb-3 border-bottom">
                    <h6 class="text-primary fw-semibold mb-1">
                        <i class="ti tabler-mail me-2"></i>Mailing Address
                    </h6>
                    <p class="text-muted small mb-0">Address used for mailed correspondence and documents.</p>
                </div>
                <div class="mb-4">
                    @include('livewire.admin.practices.partials.address-fields', [
                        'prefix' => 'mailingAddressData',
                        'required' => false,
                    ])
                </div>

                {{-- Billing Address --}}
                <div class="mb-3 pb-3 border-bottom">
                    <h6 class="text-primary fw-semibold mb-1">
                        <i class="ti tabler-receipt me-2"></i>Billing Address
                    </h6>
                    <p class="text-muted small mb-0">Address used for billing and remittance correspondence.</p>
                </div>
                <div class="mb-4">
                    @include('livewire.admin.practices.partials.address-fields', [
                        'prefix' => 'billingAddressData',
                        'required' => false,
                    ])
                </div>

                {{-- Bank Details --}}
                <div class="mb-3 pb-3 border-bottom">
                    <h6 class="text-primary fw-semibold mb-1">
                        <i class="ti tabler-building-bank me-2"></i>Bank Details
                    </h6>
                    <p class="text-muted small mb-0">Banking information for payments and reimbursements.</p>
                </div>
                <div class="row g-4 mb-4">
                    <div class="col-md-4">
                        <label class="form-label fw-medium">Bank Name</label>
                        <input type="text" wire:model="formData.bank_name"
                            class="form-control @error('formData.bank_name') is-invalid @enderror"
                            placeholder="Financial institution name">
                        @error('formData.bank_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-medium">Account Number</label>
                        <input type="text" wire:model="formData.bank_account"
                            class="form-control @error('formData.bank_account') is-invalid @enderror"
                            placeholder="Bank account number">
                        @error('formData.bank_account')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-medium">Routing Number</label>
                        <input type="text" wire:model="formData.bank_routing_number"
                            class="form-control @error('formData.bank_routing_number') is-invalid @enderror"
                            placeholder="9-digit routing number"
                            inputmode="numeric">
                        @error('formData.bank_routing_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-medium">Bank Phone</label>
                        <input type="text" wire:model="formData.bank_phone"
                            class="form-control @error('formData.bank_phone') is-invalid @enderror"
                            placeholder="Bank contact number">
                        @error('formData.bank_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-medium">Bank Address</label>
                        <textarea wire:model="formData.bank_address" rows="2"
                            class="form-control @error('formData.bank_address') is-invalid @enderror"
                            placeholder="Full bank branch address"></textarea>
                        @error('formData.bank_address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                {{-- License & Documents --}}
                <div class="mb-3 pb-3 border-bottom">
                    <h6 class="text-primary fw-semibold mb-1">
                        <i class="ti tabler-certificate me-2"></i>License & Documents
                    </h6>
                    <p class="text-muted small mb-0">Practice license and supporting credentialing documents.</p>
                </div>
                <div class="row g-4 mb-4">
                    <div class="col-md-4">
                        <label class="form-label fw-medium">License Number</label>
                        <input type="text" wire:model="formData.license_number"
                            class="form-control @error('formData.license_number') is-invalid @enderror"
                            placeholder="Practice license number">
                        @error('formData.license_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-8">
                        <label class="form-label fw-medium">Upload Document</label>
                        <input type="file" wire:model="document"
                            class="form-control @error('document') is-invalid @enderror"
                            accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                        <div class="form-text">Accepted: PDF, DOC, DOCX, JPG, PNG (max 10MB). Leave empty to keep the current file.</div>
                        @error('document')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div wire:loading wire:target="document" class="text-muted small mt-1">Uploading...</div>
                        @if ($existingDocumentPath)
                            <div class="mt-2">
                                <small class="text-muted d-block">Current document:</small>
                                <a href="{{ asset('storage/' . $existingDocumentPath) }}" target="_blank" class="fw-semibold">
                                    <i class="ti tabler-file-text me-1"></i>{{ $existingDocumentName ?: 'View document' }}
                                </a>
                            </div>
                        @endif
                    </div>
                </div>

                @if ($practiceId)
                    <livewire:admin.practices.practice-contacts-section :practice-id="$practiceId" :key="'contacts-'.$practiceId" />
                    <livewire:admin.practices.practice-locations-section :practice-id="$practiceId" :key="'locations-'.$practiceId" />
                @endif

                {{-- Actions --}}
                <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 pt-3 border-top">
                    <p class="text-muted small mb-0"><span class="text-danger">*</span> Required fields</p>
                    <div class="d-flex gap-2">
                        <a href="{{ route('admin.practices') }}" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="ti tabler-device-floppy me-1"></i>Save Changes
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
