{{-- Add Provider: multiple service locations (saved when the provider is created). --}}
<div class="mb-3 pb-3 border-bottom d-flex justify-content-between align-items-center gap-3">
    <div>
        <h6 class="text-primary fw-semibold mb-1">
            <i class="ti tabler-map-pins me-2"></i>Service Locations
        </h6>
        <p class="text-muted small mb-0">Every location this provider works at. Pick a practice address or facility, or enter a new address.</p>
    </div>
    <button type="button" wire:click="openLocationModal" class="btn btn-sm btn-outline-primary text-nowrap flex-shrink-0">
        <i class="ti tabler-plus me-1"></i>Add Location
    </button>
</div>

<div class="table-responsive mb-4">
    <table class="table table-sm table-hover align-middle mb-0">
        <thead class="table-light">
            <tr>
                <th>Location</th>
                <th>Address</th>
                <th>Role</th>
                <th>Primary</th>
                <th class="text-end">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($serviceLocations as $index => $loc)
                <tr wire:key="service-location-{{ $index }}-{{ $loc['location_key'] ?: md5($loc['address1'].$loc['zip_code']) }}">
                    <td>
                        <div class="fw-semibold">{{ $loc['name'] }}</div>
                        @if ($loc['source'] === 'new')
                            <span class="badge bg-label-info">New facility</span>
                        @endif
                    </td>
                    <td>
                        <small>
                            {{ $loc['address1'] }}{{ $loc['address2'] ? ', '.$loc['address2'] : '' }},
                            {{ $loc['city'] }}, {{ $loc['state'] }} {{ $loc['zip_code'] }}
                        </small>
                    </td>
                    <td><small>{{ $loc['role'] ?: '—' }}</small></td>
                    <td>
                        @if ($loc['is_primary'])
                            <span class="badge bg-success">Primary</span>
                        @else
                            <button type="button" wire:click="setPrimaryServiceLocation({{ $index }})" class="btn btn-sm btn-link p-0">
                                Make primary
                            </button>
                        @endif
                    </td>
                    <td class="text-end">
                        <button type="button" wire:click="removeServiceLocation({{ $index }})"
                            class="btn btn-sm btn-icon btn-outline-danger" title="Remove">
                            <i class="ti tabler-trash"></i>
                        </button>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center text-muted py-3">
                        No service locations added yet.
                        @if (! $practiceId)
                            Select a practice first.
                        @endif
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if ($showLocationModal)
    {{-- No <form> here: this sits inside the Add Provider form. Enter adds the location instead of submitting. --}}
    <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,.5); z-index: 2000; position: fixed; inset: 0;">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content" wire:keydown.enter.prevent="addServiceLocation">
                <div class="modal-header">
                    <h5 class="modal-title">Add Service Location</h5>
                    <button type="button" class="btn-close" wire:click="closeLocationModal"></button>
                </div>
                <div class="modal-body">
                    <div class="btn-group w-100 mb-4" role="group">
                        <input type="radio" class="btn-check" id="svcLocModeExisting" value="existing" wire:model.live="locationForm.mode">
                        <label class="btn btn-outline-primary" for="svcLocModeExisting">
                            <i class="ti tabler-building me-1"></i>Choose from practice
                        </label>
                        <input type="radio" class="btn-check" id="svcLocModeNew" value="new" wire:model.live="locationForm.mode">
                        <label class="btn btn-outline-primary" for="svcLocModeNew">
                            <i class="ti tabler-map-pin-plus me-1"></i>Enter new address
                        </label>
                    </div>

                    <div class="row g-3">
                        @if (($locationForm['mode'] ?? 'existing') === 'existing')
                            <div class="col-12">
                                <label class="form-label">Practice address or facility <span class="text-danger">*</span></label>
                                <select wire:model="locationForm.location_key"
                                    class="form-select @error('locationForm.location_key') is-invalid @enderror">
                                    <option value="">{{ $availableLocationOptions === [] ? 'All practice locations already added' : 'Select a location...' }}</option>
                                    @foreach ($availableLocationOptions as $key => $option)
                                        <option value="{{ $key }}">{{ $option['label'] }}</option>
                                    @endforeach
                                </select>
                                @error('locationForm.location_key')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        @else
                            <div class="col-12">
                                <label class="form-label">Location name <span class="text-danger">*</span></label>
                                <input type="text" wire:model="locationForm.name"
                                    class="form-control @error('locationForm.name') is-invalid @enderror"
                                    placeholder="e.g. North Clinic">
                                @error('locationForm.name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <div class="form-text">Also saved as a facility location on the selected practice.</div>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Street address <span class="text-danger">*</span></label>
                                <input type="text" wire:model="locationForm.address1"
                                    class="form-control @error('locationForm.address1') is-invalid @enderror">
                                @error('locationForm.address1')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Suite / Unit</label>
                                <input type="text" wire:model="locationForm.address2" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Zip code <span class="text-danger">*</span></label>
                                <input type="text" wire:model="locationForm.zip_code"
                                    wire:blur="lookupZip('locationForm')"
                                    class="form-control @error('locationForm.zip_code') is-invalid @enderror"
                                    maxlength="10" inputmode="numeric">
                                @error('locationForm.zip_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                @if (! empty($zipLookupMessages['locationForm'] ?? null))
                                    <div class="form-text text-warning">{{ $zipLookupMessages['locationForm'] }}</div>
                                @endif
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">City <span class="text-danger">*</span></label>
                                <input type="text" wire:model="locationForm.city"
                                    class="form-control @error('locationForm.city') is-invalid @enderror">
                                @error('locationForm.city')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">State <span class="text-danger">*</span></label>
                                <x-admin.state-select wire:model="locationForm.state"
                                    class="{{ $errors->has('locationForm.state') ? 'is-invalid' : '' }}" />
                                @error('locationForm.state')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                        @endif

                        <div class="col-md-8">
                            <label class="form-label">Role</label>
                            <input type="text" wire:model="locationForm.role" class="form-control"
                                placeholder="e.g. Attending, Covering">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="svcLocPrimary"
                                    wire:model="locationForm.is_primary"
                                    @disabled($serviceLocations === [])>
                                <label class="form-check-label" for="svcLocPrimary">Primary location</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" wire:click="closeLocationModal">Cancel</button>
                    <button type="button" class="btn btn-primary" wire:click="addServiceLocation">
                        <i class="ti tabler-plus me-1"></i>Add Location
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif
