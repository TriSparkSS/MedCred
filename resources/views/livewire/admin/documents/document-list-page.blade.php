<div class="container-fluid flex-grow-1 px-3 px-md-4 py-3 py-md-4">

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-start gap-3">
            <div>
                <h4 class="fw-bold text-primary mb-1"><i class="ti tabler-files me-2"></i>Document Repository</h4>
                <p class="text-muted mb-0">Upload, version, and track provider, practice, and case documents.</p>
            </div>
            <button wire:click="openUploadModal" class="btn btn-primary">
                <i class="ti tabler-upload me-1"></i>Upload Document
            </button>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <p class="text-uppercase text-muted small mb-1">Total Documents</p>
                    <h3 class="fw-bold mb-0">{{ $stats['total'] }}</h3>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <p class="text-uppercase text-muted small mb-1">Expiring Soon</p>
                    <h3 class="fw-bold mb-0 text-warning">{{ $stats['expiring'] }}</h3>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <p class="text-uppercase text-muted small mb-1">Expired</p>
                    <h3 class="fw-bold mb-0 text-danger">{{ $stats['expired'] }}</h3>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body d-flex align-items-center">
                    @php
                        $compliant =
                            $stats['total'] > 0
                                ? round((($stats['total'] - $stats['expired']) / $stats['total']) * 100)
                                : 100;
                    @endphp
                    <div>
                        <p class="text-uppercase text-muted small mb-1">Compliance</p>
                        <div class="d-flex align-items-center gap-2">
                            <span
                                class="rounded-circle d-inline-block bg-{{ $compliant >= 90 ? 'success' : 'warning' }}"
                                style="width:10px; height:10px;"></span>
                            <span class="fw-semibold">{{ $compliant }}% Current</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="ti tabler-search"></i></span>
                        <input type="search" wire:model.live="search" class="form-control border-start-0"
                            placeholder="Search by title or provider...">
                    </div>
                </div>
                <div class="col-md-3">
                    <select wire:model.live="filterType" class="form-select">
                        <option value="">All Types</option>
                        @foreach ($documentTypes as $type)
                            <option value="{{ $type->id }}">{{ $type->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <select wire:model.live="filterExpiry" class="form-select">
                        <option value="">All Expiry Status</option>
                        <option value="expiring">Expiring Soon (30 days)</option>
                        <option value="expired">Expired</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Document</th>
                        <th>Type</th>
                        <th>Linked To</th>
                        <th>Effective</th>
                        <th>Expiration</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($documents as $document)
                        @php
                            $version = $document->versions->first();
                            $expired = $document->isExpired();
                            $expiring = $document->isExpiringSoon(30);
                        @endphp
                        <tr wire:key="doc-{{ $document->id }}">
                            <td>
                                <div class="fw-semibold">
                                    <i class="ti tabler-file-text me-1 text-muted"></i>
                                    {{ $document->title }}
                                </div>
                                @if ($version)
                                    <small class="text-muted">v{{ $version->version_number }} ·
                                        {{ $version->original_name }}</small>
                                @endif
                            </td>
                            <td>{{ $document->documentType->name ?? '—' }}</td>
                            <td class="small">
                                @if ($document->practice)
                                    <div><span class="text-muted">Practice:</span>
                                        {{ $document->practice->legal_name ?? 'Practice' }}</div>
                                @endif
                                @if ($document->provider)
                                    <div><span class="text-muted">Provider:</span>
                                        {{ $document->provider->user->name ?? 'Provider' }}</div>
                                @endif
                                @if ($document->credentialingCase)
                                    <div class="text-muted">Case {{ $document->credentialingCase->case_number }}</div>
                                @endif
                                @if (!$document->practice && !$document->provider && !$document->credentialingCase)
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>{{ $document->effective_date?->format('m/d/Y') ?: '—' }}</td>
                            <td class="{{ $expired ? 'text-danger' : ($expiring ? 'text-warning' : '') }}">
                                {{ $document->expiry_date?->format('m/d/Y') ?: '—' }}
                            </td>
                            <td>
                                @php $vStatus = $document->verification_status ?? 'uploaded'; @endphp
                                <span
                                    class="badge bg-label-{{ match ($vStatus) {'verified' => 'success','rejected' => 'danger','expired' => 'warning',default => 'secondary'} }}">
                                    {{ ucfirst(str_replace('_', ' ', $vStatus)) }}
                                </span>
                                @if ($expired)
                                    <span class="badge bg-label-danger ms-1">Expired</span>
                                @elseif ($expiring)
                                    <span class="badge bg-label-warning text-dark ms-1">Expiring</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    @if ($vStatus !== 'verified')
                                        <button wire:click="verifyDocument({{ $document->id }})"
                                            class="btn btn-outline-success" title="Verify">
                                            <i class="ti tabler-check"></i>
                                        </button>
                                    @endif
                                    @if ($vStatus !== 'rejected')
                                        <button wire:click="openRejectModal({{ $document->id }})"
                                            class="btn btn-outline-danger" title="Reject">
                                            <i class="ti tabler-x"></i>
                                        </button>
                                    @endif
                                    @if ($version)
                                        <a href="{{ asset('storage/' . $version->file_path) }}" target="_blank"
                                            class="btn btn-outline-secondary" title="Download">
                                            <i class="ti tabler-download"></i>
                                        </a>
                                    @endif
                                    <button wire:click="openVersionModal({{ $document->id }})"
                                        class="btn btn-outline-primary" title="New Version">
                                        <i class="ti tabler-versions"></i>
                                    </button>
                                    <button wire:click="deleteDocument({{ $document->id }})"
                                        wire:confirm="Delete this document?" class="btn btn-outline-danger"
                                        title="Delete">
                                        <i class="ti tabler-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">
                                <i class="ti tabler-file-off d-block mb-2" style="font-size:2rem"></i>
                                No documents found. Upload your first document to get started.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white">{{ $documents->links('livewire::bootstrap') }}</div>
    </div>

    {{-- Upload Modal --}}
    @if ($showModal)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,.5);">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Upload Document</h5>
                        <button type="button" class="btn-close" wire:click="$set('showModal', false)"></button>
                    </div>
                    <form wire:submit.prevent="saveDocument">
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-8">
                                    <label class="form-label">Title <span class="text-danger">*</span></label>
                                    <input type="text" wire:model="formData.title"
                                        class="form-control @error('formData.title') is-invalid @enderror">
                                    @error('formData.title')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Document Type</label>
                                    <select wire:model="formData.document_type_id" class="form-select">
                                        <option value="">Select type...</option>
                                        @foreach ($documentTypes as $type)
                                            <option value="{{ $type->id }}">{{ $type->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Practice</label>
                                    <select wire:model="formData.practice_id" class="form-select">
                                        <option value="">None</option>
                                        @foreach ($practices as $practice)
                                            <option value="{{ $practice->id }}">{{ $practice->legal_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Provider</label>
                                    <select wire:model="formData.provider_id" class="form-select">
                                        <option value="">None</option>
                                        @foreach ($providers as $provider)
                                            <option value="{{ $provider->id }}">
                                                {{ $provider->user->name ?? 'Provider #' . $provider->id }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Credentialing Case</label>
                                    <select wire:model="formData.credentialing_case_id" class="form-select">
                                        <option value="">None</option>
                                        @foreach ($cases as $case)
                                            <option value="{{ $case->id }}">{{ $case->case_number }} —
                                                {{ $case->provider->user->name ?? '' }}</option>
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
                                <div class="col-md-4">
                                    <label class="form-label">State</label>
                                    <input type="text" wire:model="formData.state" class="form-control"
                                        maxlength="50">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">File <span class="text-danger">*</span></label>
                                    <input type="file" wire:model="uploadFile"
                                        class="form-control @error('uploadFile') is-invalid @enderror"
                                        accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                    @error('uploadFile')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <div wire:loading wire:target="uploadFile" class="text-muted small mt-1">
                                        Uploading...</div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary"
                                wire:click="$set('showModal', false)">Cancel</button>
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

    {{-- Version Modal --}}
    @if ($showVersionModal)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,.5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Upload New Version</h5>
                        <button type="button" class="btn-close"
                            wire:click="$set('showVersionModal', false)"></button>
                    </div>
                    <form wire:submit.prevent="uploadNewVersion">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">File <span class="text-danger">*</span></label>
                                <input type="file" wire:model="versionFile"
                                    class="form-control @error('versionFile') is-invalid @enderror"
                                    accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                @error('versionFile')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div>
                                <label class="form-label">Notes</label>
                                <textarea wire:model="versionNotes" rows="2" class="form-control" placeholder="Optional version notes..."></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary"
                                wire:click="$set('showVersionModal', false)">Cancel</button>
                            <button type="submit" class="btn btn-primary">Upload Version</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    {{-- Reject Modal --}}
    @if ($rejectDocumentId)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,.5);">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Reject Document</h5>
                        <button type="button" class="btn-close"
                            wire:click="$set('rejectDocumentId', null)"></button>
                    </div>
                    <div class="modal-body">
                        <label class="form-label">Rejection reason <span class="text-danger">*</span></label>
                        <textarea wire:model="rejectionReason" rows="3"
                            class="form-control @error('rejectionReason') is-invalid @enderror"></textarea>
                        @error('rejectionReason')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary"
                            wire:click="$set('rejectDocumentId', null)">Cancel</button>
                        <button type="button" wire:click="rejectDocument" class="btn btn-danger">Reject</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
