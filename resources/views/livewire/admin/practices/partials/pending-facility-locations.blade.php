{{-- Add Practice: facility location rows saved with the new practice (same fields as the Edit page Facility Locations). --}}
<div class="mb-4" wire:key="pending-facility-locations">
    <div class="mb-3 pb-3 border-bottom d-flex justify-content-between align-items-center gap-3">
        <div>
            <h6 class="text-primary fw-semibold mb-1"><i class="ti tabler-map-pins me-2"></i>Facility Locations</h6>
            <p class="text-muted small mb-0">Service locations for this practice. Enter a ZIP code to fill city, state, and county.</p>
        </div>
        <button type="button" wire:click="addFacilityLocationRow" class="btn btn-sm btn-outline-primary text-nowrap flex-shrink-0">
            <i class="ti tabler-plus me-1"></i>Add Location
        </button>
    </div>

    @forelse ($facilityLocations as $index => $location)
        @php $rowKey = 'facilityLocations.'.$index; @endphp
        <div class="border rounded p-3 mb-3" wire:key="pending-facility-location-{{ $index }}">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="fw-semibold small text-muted"><i class="ti tabler-map-pin me-1"></i>Location #{{ $index + 1 }}</span>
                <button type="button" wire:click="removeFacilityLocationRow({{ $index }})"
                    class="btn btn-sm btn-icon btn-outline-danger" title="Remove">
                    <i class="ti tabler-trash"></i>
                </button>
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-medium">Location Name <span class="text-danger">*</span></label>
                    <input type="text" wire:model="{{ $rowKey }}.name"
                        class="form-control @error($rowKey.'.name') is-invalid @enderror">
                    @error($rowKey.'.name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-medium">Status <span class="text-danger">*</span></label>
                    <select wire:model="{{ $rowKey }}.status" class="form-select @error($rowKey.'.status') is-invalid @enderror">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                    @error($rowKey.'.status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <div class="form-check form-switch mb-2">
                        <input type="checkbox" class="form-check-input" wire:model="{{ $rowKey }}.is_primary" id="pendingLocationPrimary{{ $index }}">
                        <label class="form-check-label" for="pendingLocationPrimary{{ $index }}">Primary Location</label>
                    </div>
                </div>
                <div class="col-md-8">
                    <label class="form-label fw-medium">Address Line 1 <span class="text-danger">*</span></label>
                    <input type="text" wire:model="{{ $rowKey }}.address1"
                        class="form-control @error($rowKey.'.address1') is-invalid @enderror">
                    @error($rowKey.'.address1')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-medium">Address Line 2</label>
                    <input type="text" wire:model="{{ $rowKey }}.address2"
                        class="form-control @error($rowKey.'.address2') is-invalid @enderror">
                    @error($rowKey.'.address2')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-medium">Zip Code <span class="text-danger">*</span></label>
                    <input type="text" wire:model="{{ $rowKey }}.zip_code"
                        wire:blur="lookupZip('{{ $rowKey }}')"
                        maxlength="10" inputmode="numeric"
                        class="form-control @error($rowKey.'.zip_code') is-invalid @enderror">
                    @error($rowKey.'.zip_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div wire:loading wire:target="lookupZip('{{ $rowKey }}')" class="text-muted small mt-1">Looking up ZIP...</div>
                    @if (! empty($zipLookupMessages[$rowKey] ?? null))
                        <div class="form-text text-warning">{{ $zipLookupMessages[$rowKey] }}</div>
                    @endif
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-medium">City <span class="text-danger">*</span></label>
                    <input type="text" wire:model="{{ $rowKey }}.city"
                        class="form-control @error($rowKey.'.city') is-invalid @enderror">
                    @error($rowKey.'.city')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-medium">State <span class="text-danger">*</span></label>
                    <x-admin.state-select wire:model="{{ $rowKey }}.state"
                        class="{{ $errors->has($rowKey.'.state') ? 'is-invalid' : '' }}" />
                    @error($rowKey.'.state')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-medium">County</label>
                    <input type="text" wire:model="{{ $rowKey }}.county"
                        class="form-control @error($rowKey.'.county') is-invalid @enderror">
                    @error($rowKey.'.county')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-medium">Country <span class="text-danger">*</span></label>
                    <input type="text" wire:model="{{ $rowKey }}.country"
                        class="form-control @error($rowKey.'.country') is-invalid @enderror">
                    @error($rowKey.'.country')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-medium">Phone</label>
                    <input type="text" wire:model="{{ $rowKey }}.phone"
                        class="form-control @error($rowKey.'.phone') is-invalid @enderror">
                    @error($rowKey.'.phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-medium">Fax</label>
                    <input type="text" wire:model="{{ $rowKey }}.fax"
                        class="form-control @error($rowKey.'.fax') is-invalid @enderror">
                    @error($rowKey.'.fax')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-medium">NPI</label>
                    <input type="text" wire:model="{{ $rowKey }}.npi"
                        class="form-control @error($rowKey.'.npi') is-invalid @enderror">
                    @error($rowKey.'.npi')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-medium">Taxonomy Code</label>
                    <input type="text" wire:model="{{ $rowKey }}.taxonomy_code"
                        class="form-control @error($rowKey.'.taxonomy_code') is-invalid @enderror">
                    @error($rowKey.'.taxonomy_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
    @empty
        <p class="text-muted small text-center py-2 mb-0">No facility locations added yet.</p>
    @endforelse
</div>
