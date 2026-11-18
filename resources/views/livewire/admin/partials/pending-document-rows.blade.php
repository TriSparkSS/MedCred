{{--
    Add Practice / Add Provider: document rows saved with the new record.
    Params: $group (component property), $heading, $icon, $description, $addLabel,
    $lockedTypeName (nullable), $documentTypes (collection, ignored when locked), $showState (bool).
--}}
@php
    $rows = $this->{$group};
    $lockedTypeName = $lockedTypeName ?? null;
    $showState = $showState ?? false;
@endphp
<div class="mb-4" wire:key="pending-docs-{{ $group }}">
    <div class="mb-3 pb-3 border-bottom d-flex justify-content-between align-items-center gap-3">
        <div>
            <h6 class="text-primary fw-semibold mb-1">
                <i class="ti {{ $icon }} me-2"></i>{{ $heading }}
            </h6>
            <p class="text-muted small mb-0">{{ $description }}</p>
        </div>
        <button type="button" wire:click="addDocumentRow('{{ $group }}')"
            class="btn btn-sm btn-outline-primary text-nowrap flex-shrink-0">
            <i class="ti tabler-plus me-1"></i>{{ $addLabel }}
        </button>
    </div>

    @forelse ($rows as $index => $row)
        <div class="border rounded p-3 mb-3" wire:key="pending-doc-{{ $group }}-{{ $index }}">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="fw-semibold small text-muted">
                    <i class="ti tabler-file-text me-1"></i>{{ $lockedTypeName ?: 'Document' }} #{{ $index + 1 }}
                </span>
                <button type="button" wire:click="removeDocumentRow('{{ $group }}', {{ $index }})"
                    class="btn btn-sm btn-icon btn-outline-danger" title="Remove">
                    <i class="ti tabler-trash"></i>
                </button>
            </div>
            <div class="row g-3">
                @unless ($lockedTypeName)
                    <div class="col-md-4">
                        <label class="form-label fw-medium">Document Type</label>
                        <select wire:model.live="{{ $group }}.{{ $index }}.document_type_id"
                            class="form-select @error($group.'.'.$index.'.document_type_id') is-invalid @enderror">
                            <option value="">Select type...</option>
                            @foreach ($documentTypes as $type)
                                <option value="{{ $type->id }}">{{ $type->name }}</option>
                            @endforeach
                        </select>
                        @error($group.'.'.$index.'.document_type_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                @endunless
                <div class="col-md-4">
                    <label class="form-label fw-medium">Document name <span class="text-danger">*</span></label>
                    <input type="text" wire:model="{{ $group }}.{{ $index }}.title"
                        class="form-control @error($group.'.'.$index.'.title') is-invalid @enderror">
                    @error($group.'.'.$index.'.title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-medium">Number</label>
                    <input type="text" wire:model="{{ $group }}.{{ $index }}.document_number"
                        class="form-control @error($group.'.'.$index.'.document_number') is-invalid @enderror">
                    @error($group.'.'.$index.'.document_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                @if ($showState)
                    <div class="col-md-4">
                        <label class="form-label fw-medium">State</label>
                        <x-admin.state-select wire:model="{{ $group }}.{{ $index }}.state"
                            class="{{ $errors->has($group.'.'.$index.'.state') ? 'is-invalid' : '' }}" />
                        @error($group.'.'.$index.'.state')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        <div class="form-text">Required for state-specific document types.</div>
                    </div>
                @endif
                <div class="col-md-2">
                    <label class="form-label fw-medium">Effective date</label>
                    <input type="date" wire:model="{{ $group }}.{{ $index }}.effective_date"
                        class="form-control @error($group.'.'.$index.'.effective_date') is-invalid @enderror">
                    @error($group.'.'.$index.'.effective_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-medium">Expiration</label>
                    <input type="date" wire:model="{{ $group }}.{{ $index }}.expiry_date"
                        class="form-control @error($group.'.'.$index.'.expiry_date') is-invalid @enderror">
                    @error($group.'.'.$index.'.expiry_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-8">
                    <label class="form-label fw-medium">File <span class="text-danger">*</span></label>
                    <input type="file" wire:model="{{ $group }}.{{ $index }}.file"
                        class="form-control @error($group.'.'.$index.'.file') is-invalid @enderror"
                        accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                    <div class="form-text">Accepted: PDF, DOC, DOCX, JPG, PNG (max 10MB)</div>
                    @error($group.'.'.$index.'.file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div wire:loading wire:target="{{ $group }}.{{ $index }}.file" class="text-muted small mt-1">Uploading...</div>
                </div>
            </div>
        </div>
    @empty
        <p class="text-muted small text-center py-2 mb-0">No {{ strtolower($heading) }} added yet.</p>
    @endforelse
</div>
