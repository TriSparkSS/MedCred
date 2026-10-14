<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h6 class="fw-semibold mb-0">Licenses &amp; other credentials</h6>
        <small class="text-muted">Editable License, DEA, and CDS rows for regular maintenance. Upload supporting files in Provider Documents.</small>
    </div>
    <button type="button" wire:click="addCredentialRow" class="btn btn-sm btn-outline-primary">
        <i class="ti tabler-plus me-1"></i>Add row
    </button>
</div>
@foreach ($credentialRows as $index => $row)
    <div class="row g-3 mb-3 align-items-end" wire:key="cred-row-{{ $row['id'] ?? 'new' }}-{{ $index }}">
        <div class="col-md-3">
            <label class="form-label">Type</label>
            <select wire:model="credentialRows.{{ $index }}.credential_type" class="form-select">
                @foreach ($credentialTypes as $type)
                    <option value="{{ $type->value }}">{{ $type->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">State</label>
            <select wire:model="credentialRows.{{ $index }}.state" class="form-select">
                <option value="">Select...</option>
                @foreach ($states as $code => $name)
                    <option value="{{ $code }}">{{ $code }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Number</label>
            <input type="text" wire:model="credentialRows.{{ $index }}.number" class="form-control" placeholder="Number">
        </div>
        <div class="col-md-2">
            <label class="form-label">Expiry</label>
            <input type="date" wire:model="credentialRows.{{ $index }}.expiry_date" class="form-control">
        </div>
        <div class="col-md-1">
            <button type="button" wire:click="removeCredentialRow({{ $index }})" class="btn btn-outline-danger w-100" title="Remove">
                <i class="ti tabler-trash"></i>
            </button>
        </div>
    </div>
@endforeach
