@php
    use App\Enums\DocumentVerificationStatus;

    $verificationBadge = function ($status): string {
        $key = $status instanceof DocumentVerificationStatus ? $status->value : ($status ?? '');

        return match ($key) {
            DocumentVerificationStatus::Verified->value => 'success',
            DocumentVerificationStatus::Uploaded->value => 'info',
            DocumentVerificationStatus::Rejected->value => 'danger',
            DocumentVerificationStatus::Expired->value => 'danger',
            DocumentVerificationStatus::Superseded->value => 'secondary',
            DocumentVerificationStatus::Missing->value => 'warning',
            default => 'secondary',
        };
    };

    $verificationLabel = function ($status): string {
        if ($status instanceof DocumentVerificationStatus) {
            return $status->label();
        }

        return DocumentVerificationStatus::tryFrom((string) $status)?->label() ?? ucfirst((string) $status ?: 'Unknown');
    };

    $ownerLabel = $isProvider ? 'Provider' : 'Practice';
@endphp

<div class="card shadow-sm border-0">
    <div class="card-header bg-white d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2">
        <div>
            <h6 class="mb-0 fw-semibold">{{ $ownerLabel }} Documents</h6>
            <small class="text-muted">View and upload files. These records also appear in the Document Hub.</small>
        </div>
        <div class="d-flex flex-wrap gap-2">
            @if (auth('admin')->user()?->can('admin.documents.view'))
                <a href="{{ $hubUrl }}" class="btn btn-sm btn-outline-secondary">
                    <i class="ti tabler-external-link me-1"></i>Open in Document Hub
                </a>
            @endif
            @if ($canUpload)
                <button type="button" wire:click="openUploadModal" class="btn btn-sm btn-primary">
                    <i class="ti tabler-upload me-1"></i>Upload Document
                </button>
            @endif
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Document</th>
                    <th>Type</th>
                    @if ($isProvider)
                        <th>State</th>
                    @endif
                    <th>Effective</th>
                    <th>Expiration</th>
                    <th>Verification</th>
                    <th>Expiry Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($documents as $document)
                    @php
                        $version = $document->versions->first();
                        $expired = $document->isExpired();
                        $expiring = $document->isExpiringSoon(30);
                        $vStatus = $document->verification_status instanceof DocumentVerificationStatus
                            ? $document->verification_status->value
                            : $document->verification_status;
                    @endphp
                    <tr>
                        <td class="fw-semibold">{{ $document->title }}</td>
                        <td>{{ $document->documentType->name ?? '—' }}</td>
                        @if ($isProvider)
                            <td>
                                @if ($document->state)
                                    <span class="badge bg-label-primary">{{ $document->state }}</span>
                                @elseif ($document->documentType?->is_state_specific)
                                    <span class="badge bg-label-warning">Missing</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                        @endif
                        <td>{{ $document->effective_date?->format('m/d/Y') ?: '—' }}</td>
                        <td class="{{ $expired ? 'text-danger' : ($expiring ? 'text-warning' : '') }}">
                            {{ $document->expiry_date?->format('m/d/Y') ?: '—' }}
                        </td>
                        <td>
                            <span class="badge bg-label-{{ $verificationBadge($document->verification_status) }}">
                                {{ $verificationLabel($document->verification_status) }}
                            </span>
                        </td>
                        <td>
                            @if ($expired)
                                <span class="badge bg-label-danger">Expired</span>
                            @elseif ($expiring)
                                <span class="badge bg-label-warning text-dark">Expiring Soon</span>
                            @else
                                <span class="badge bg-label-success">Active</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                @if ($canVerify && $vStatus !== 'verified')
                                    <button type="button" wire:click="verifyDocument({{ $document->id }})" class="btn btn-outline-success" title="Verify">
                                        <i class="ti tabler-check"></i>
                                    </button>
                                @endif
                                @if ($canVerify && $vStatus !== 'rejected')
                                    <button type="button" wire:click="openRejectModal({{ $document->id }})" class="btn btn-outline-danger" title="Reject">
                                        <i class="ti tabler-x"></i>
                                    </button>
                                @endif
                                @if ($version)
                                    <a href="{{ asset('storage/' . $version->file_path) }}" target="_blank" class="btn btn-outline-secondary" title="Download">
                                        <i class="ti tabler-download"></i>
                                    </a>
                                @endif
                                @if ($canUpload)
                                    <button type="button" wire:click="openVersionModal({{ $document->id }})" class="btn btn-outline-primary" title="New Version">
                                        <i class="ti tabler-versions"></i>
                                    </button>
                                    <button type="button" wire:click="deleteDocument({{ $document->id }})" wire:confirm="Delete this document?" class="btn btn-outline-danger" title="Delete">
                                        <i class="ti tabler-trash"></i>
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    @if ($legacy)
                        <tr>
                            <td class="fw-semibold">{{ $legacy['name'] }}</td>
                            <td>—</td>
                            <td>—</td>
                            <td>—</td>
                            <td><span class="badge bg-label-secondary">Profile file</span></td>
                            <td><span class="badge bg-label-success">Active</span></td>
                            <td class="text-end">
                                <a href="{{ asset('storage/' . $legacy['path']) }}" target="_blank" class="btn btn-sm btn-outline-secondary">
                                    <i class="ti tabler-download"></i>
                                </a>
                            </td>
                        </tr>
                    @else
                        <tr>
                            <td colspan="{{ $isProvider ? 8 : 7 }}" class="text-center text-muted py-4">
                                No documents on file for this {{ strtolower($ownerLabel) }}.
                            </td>
                        </tr>
                    @endif
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if ($showModal)
    <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,.5);">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Upload {{ $ownerLabel }} Document</h5>
                    <button type="button" class="btn-close" wire:click="$set('showModal', false)"></button>
                </div>
                <form wire:submit.prevent="saveDocument">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label">Title <span class="text-danger">*</span></label>
                                <input type="text" wire:model="formData.title" class="form-control @error('formData.title') is-invalid @enderror">
                                @error('formData.title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Document Type</label>
                                <select wire:model.live="formData.document_type_id" class="form-select">
                                    <option value="">Select type...</option>
                                    @foreach ($documentTypes as $type)
                                        <option value="{{ $type->id }}">{{ $type->name }}{{ $type->is_state_specific ? ' (state-specific)' : '' }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Effective Date</label>
                                <input type="date" wire:model="formData.effective_date" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Expiry Date</label>
                                <input type="date" wire:model="formData.expiry_date" class="form-control">
                            </div>
                            @if ($isProvider)
                                <div class="col-md-4">
                                    <label class="form-label">State @if($selectedType?->is_state_specific)<span class="text-danger">*</span>@endif</label>
                                    <x-admin.state-select wire:model="formData.state" class="{{ $errors->has('formData.state') ? 'is-invalid' : '' }}" />
                                    @error('formData.state')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    @if ($selectedType?->is_state_specific)
                                        <small class="text-muted">Required for {{ $selectedType->name }}.</small>
                                    @endif
                                </div>
                                @if ($selectedType?->is_state_specific)
                                    <div class="col-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" wire:model="formData.sync_credential" id="syncCredential-{{ $providerId }}">
                                            <label class="form-check-label" for="syncCredential-{{ $providerId }}">
                                                Create or update the matching provider credential for this state
                                            </label>
                                        </div>
                                    </div>
                                @endif
                            @endif
                            <div class="col-12">
                                <label class="form-label">File <span class="text-danger">*</span></label>
                                <input type="file" wire:model="uploadFile" class="form-control @error('uploadFile') is-invalid @enderror" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                @error('uploadFile')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <div wire:loading wire:target="uploadFile" class="text-muted small mt-1">Uploading...</div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" wire:click="$set('showModal', false)">Cancel</button>
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="saveDocument">Save Document</span>
                            <span wire:loading wire:target="saveDocument">Saving...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif

@if ($showVersionModal)
    <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,.5);">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Upload New Version</h5>
                    <button type="button" class="btn-close" wire:click="$set('showVersionModal', false)"></button>
                </div>
                <form wire:submit.prevent="uploadNewVersion">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">File <span class="text-danger">*</span></label>
                            <input type="file" wire:model="versionFile" class="form-control @error('versionFile') is-invalid @enderror" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                            @error('versionFile')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div>
                            <label class="form-label">Notes</label>
                            <textarea wire:model="versionNotes" rows="2" class="form-control" placeholder="Optional version notes..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" wire:click="$set('showVersionModal', false)">Cancel</button>
                        <button type="submit" class="btn btn-primary">Upload Version</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif

@if ($rejectDocumentId)
    <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,.5);">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Reject Document</h5>
                    <button type="button" class="btn-close" wire:click="$set('rejectDocumentId', null)"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">Rejection reason <span class="text-danger">*</span></label>
                    <textarea wire:model="rejectionReason" rows="3" class="form-control @error('rejectionReason') is-invalid @enderror"></textarea>
                    @error('rejectionReason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" wire:click="$set('rejectDocumentId', null)">Cancel</button>
                    <button type="button" wire:click="rejectDocument" class="btn btn-danger">Reject</button>
                </div>
            </div>
        </div>
    </div>
@endif
