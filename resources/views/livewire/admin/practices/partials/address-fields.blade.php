@props(['prefix', 'required' => false, 'showStatus' => false])

<div class="row g-4">
    <div class="col-md-4">
        <label class="form-label fw-medium">Location Name</label>
        <input type="text" wire:model="{{ $prefix }}.location_name"
            class="form-control @error($prefix . '.location_name') is-invalid @enderror"
            placeholder="Office or site name">
        @error($prefix . '.location_name')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    @if ($showStatus)
        <div class="col-md-4">
            <label class="form-label fw-medium">Address Status</label>
            <select wire:model="{{ $prefix }}.status"
                class="form-select @error($prefix . '.status') is-invalid @enderror">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
            @error($prefix . '.status')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    @endif

    <div class="col-12">
        <label class="form-label fw-medium">
            Street Address @if ($required)<span class="text-danger">*</span>@endif
        </label>
        <input type="text" wire:model="{{ $prefix }}.address1"
            class="form-control @error($prefix . '.address1') is-invalid @enderror"
            placeholder="Address line 1">
        @error($prefix . '.address1')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12">
        <label class="form-label fw-medium">Address Line 2</label>
        <input type="text" wire:model="{{ $prefix }}.address2"
            class="form-control @error($prefix . '.address2') is-invalid @enderror"
            placeholder="Suite, unit, building, floor">
        @error($prefix . '.address2')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label fw-medium">
            Zip Code @if ($required)<span class="text-danger">*</span>@endif
        </label>
        <input type="text" wire:model="{{ $prefix }}.zip_code"
            wire:blur="lookupZip('{{ $prefix }}')"
            class="form-control @error($prefix . '.zip_code') is-invalid @enderror"
            placeholder="Zip code" maxlength="10" inputmode="numeric">
        <div class="form-text">Leave the field to auto-fill city, state, and county.</div>
        @error($prefix . '.zip_code')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        @if (! empty($zipLookupMessages[$prefix] ?? null))
            <div class="form-text text-warning">{{ $zipLookupMessages[$prefix] }}</div>
        @endif
    </div>

    <div class="col-md-4">
        <label class="form-label fw-medium">
            City @if ($required)<span class="text-danger">*</span>@endif
        </label>
        <input type="text" wire:model="{{ $prefix }}.city"
            class="form-control @error($prefix . '.city') is-invalid @enderror"
            placeholder="City">
        @error($prefix . '.city')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label fw-medium">
            State @if ($required)<span class="text-danger">*</span>@endif
        </label>
        <x-admin.state-select wire:model="{{ $prefix }}.state"
            class="{{ $errors->has($prefix.'.state') ? 'is-invalid' : '' }}" />
        @error($prefix . '.state')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label fw-medium">County</label>
        <input type="text" wire:model="{{ $prefix }}.county"
            class="form-control @error($prefix . '.county') is-invalid @enderror"
            placeholder="County">
        @error($prefix . '.county')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label fw-medium">
            Country @if ($required)<span class="text-danger">*</span>@endif
        </label>
        <input type="text" wire:model="{{ $prefix }}.country"
            class="form-control @error($prefix . '.country') is-invalid @enderror"
            placeholder="Country">
        @error($prefix . '.country')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label fw-medium">Phone</label>
        <input type="text" wire:model="{{ $prefix }}.phone"
            class="form-control @error($prefix . '.phone') is-invalid @enderror"
            placeholder="(555) 123-4567">
        @error($prefix . '.phone')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label fw-medium">Fax</label>
        <input type="text" wire:model="{{ $prefix }}.fax"
            class="form-control @error($prefix . '.fax') is-invalid @enderror"
            placeholder="Fax number">
        @error($prefix . '.fax')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>
