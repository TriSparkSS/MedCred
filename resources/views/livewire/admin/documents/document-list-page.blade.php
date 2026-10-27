<div class="container-fluid flex-grow-1 px-3 px-md-4 py-3 py-md-4">

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-start gap-3">
            <div>
                <h4 class="fw-bold text-primary mb-1"><i class="ti tabler-files me-2"></i>Document Repository</h4>
                <p class="text-muted mb-0">Upload, version, and track provider, practice, and case documents.</p>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <div class="btn-group" role="group" aria-label="View mode">
                    <button type="button" wire:click="setViewMode('table')"
                        class="btn btn-sm {{ $viewMode === 'table' ? 'btn-primary' : 'btn-outline-primary' }}">
                        <i class="ti tabler-table me-1"></i>Table View
                    </button>
                    <button type="button" wire:click="setViewMode('folders')"
                        class="btn btn-sm {{ $viewMode === 'folders' ? 'btn-primary' : 'btn-outline-primary' }}">
                        <i class="ti tabler-folders me-1"></i>Folder View
                    </button>
                </div>
                @if(auth('admin')->user()?->can('admin.documents.upload'))
                    <button type="button" wire:click="openUploadModal" class="btn btn-primary">
                        <i class="ti tabler-upload me-1"></i>Upload Document
                    </button>
                @endif
            </div>
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
                        <input type="search" wire:model.live.debounce.300ms="search" class="form-control border-start-0"
                            placeholder="Search by practice name, client code, payer, or provider...">
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
                <div class="col-md-2">
                    <select wire:model.live="filterState" class="form-select">
                        <option value="">All States</option>
                        @foreach ($states as $code => $name)
                            <option value="{{ $code }}">{{ $code }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select wire:model.live="filterExpiry" class="form-select">
                        <option value="">All Expiry Status</option>
                        <option value="expiring">Expiring Soon (30 days)</option>
                        <option value="expired">Expired</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <select wire:model.live="filterPractice" class="form-select">
                        <option value="">All Practices / client codes</option>
                        @foreach ($practices as $practice)
                            <option value="{{ $practice->id }}">
                                {{ $practice->legal_name }}@if($practice->client_code) ({{ $practice->client_code }})@endif
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <select wire:model.live="filterPayer" class="form-select">
                        <option value="">All Payers</option>
                        @foreach ($payers as $payer)
                            <option value="{{ $payer->id }}">{{ $payer->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </div>

    @if ($viewMode === 'folders')
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold"><i class="ti tabler-folders me-2 text-primary"></i>Document Folders (Provider / Practice → Type → Version)</h6>
                <small class="text-muted">{{ count($folderTree) }} owner folders</small>
            </div>
            <div class="card-body">
                @forelse ($folderTree as $ownerName => $typesMap)
                    <div class="border rounded-3 mb-3 p-3" x-data="{ openOwner: true }">
                        <div class="d-flex justify-content-between align-items-center cursor-pointer" x-on:click="openOwner = !openOwner">
                            <div class="fw-bold text-primary">
                                <i class="ti tabler-folder-filled me-2 text-warning"></i>{{ $ownerName }}
                            </div>
                            <span class="badge bg-label-secondary">{{ count($typesMap) }} {{ Str::plural('type', count($typesMap)) }}</span>
                        </div>
                        <div x-show="openOwner" class="mt-3 ps-3 border-start">
                            @foreach ($typesMap as $typeName => $typeDocs)
                                <div class="mb-3" x-data="{ openType: true }">
                                    <div class="d-flex justify-content-between align-items-center mb-2 cursor-pointer" x-on:click="openType = !openType">
                                        <span class="fw-semibold">
                                            <i class="ti tabler-folder me-1 text-info"></i>{{ $typeName }}
                                        </span>
                                        <span class="badge bg-label-info">{{ count($typeDocs) }} {{ Str::plural('document', count($typeDocs)) }}</span>
                                    </div>
                                    <div x-show="openType" class="ps-3">
                                        @foreach ($typeDocs as $doc)
                                            <div class="border rounded p-2 mb-2 bg-light">
                                                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                                                    <div>
                                                        <span class="fw-semibold"><i class="ti tabler-file-text me-1"></i>{{ $doc->title }}</span>
                                                        @if ($doc->state)
                                                            <span class="badge bg-label-primary ms-1">{{ $doc->state }}</span>
                                                        @endif
                                                        @if ($doc->visibility === 'restricted')
                                                            <span class="badge bg-label-danger ms-1"><i class="ti tabler-lock me-1"></i>Restricted</span>
                                                        @endif
                                                        @if ($doc->document_number)
                                                            <small class="text-muted ms-2">#{{ $doc->document_number }}</small>
                                                        @endif
                                                    </div>
                                                    <div class="btn-group btn-group-sm">
                                                        @if (auth('admin')->user()?->can('admin.documents.upload'))
                                                            <button type="button" wire:click="openVersionModal({{ $doc->id }})" class="btn btn-outline-primary btn-sm" title="Upload New Version">
                                                                <i class="ti tabler-versions"></i>
                                                            </button>
                                                        @endif
                                                    </div>
                                                </div>
                                                @if ($doc->versions->isNotEmpty())
                                                    <div class="mt-2 ps-3 small">
                                                        @foreach ($doc->versions as $ver)
                                                            <div class="d-flex justify-content-between align-items-center py-1 {{ ! $loop->last ? 'border-bottom' : '' }}">
                                                                <div>
                                                                    <span class="badge {{ $ver->is_current ? 'bg-success' : 'bg-secondary' }} me-1">
                                                                        v{{ $ver->version_number }}{{ $ver->is_current ? ' (Current)' : '' }}
                                                                    </span>
                                                                    <span>{{ $ver->original_name }}</span>
                                                                    @if ($ver->is_encrypted)
                                                                        <span class="badge bg-label-info ms-1" title="Encrypted at rest"><i class="ti tabler-shield-lock"></i> Encrypted</span>
                                                                    @endif
                                                                    @if ($ver->notes)
                                                                        <span class="text-muted ms-2">— {{ $ver->notes }}</span>
                                                                    @endif
                                                                </div>
                                                                <a href="{{ route('admin.documents.download', ['document' => $doc->id, 'version' => $ver->id]) }}"
                                                                    class="btn btn-xs btn-outline-secondary">
                                                                    <i class="ti tabler-download me-1"></i>Download v{{ $ver->version_number }}
                                                                </a>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <div class="text-center text-muted py-5">
                        <i class="ti tabler-folders-off d-block mb-2" style="font-size:2rem"></i>
                        No document folders match the current filters.
                    </div>
                @endforelse
            </div>
        </div>
    @else
    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Document</th>
                        <th>Type</th>
                        <th>State</th>
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
                                    @if ($document->visibility === 'restricted')
                                        <span class="badge bg-label-danger ms-1"><i class="ti tabler-lock"></i> Restricted</span>
                                    @endif
                                </div>
                                @if ($document->document_number)
                                    <small class="text-muted d-block">#{{ $document->document_number }}</small>
                                @endif
                                @if ($version)
                                    <small class="text-muted">v{{ $version->version_number }} ·
                                        {{ $version->original_name }}
                                        @if ($version->is_encrypted)
                                            · <span class="text-info"><i class="ti tabler-shield-lock"></i> Encrypted</span>
                                        @endif
                                    </small>
                                @endif
                            </td>
                            <td>{{ $document->documentType->name ?? '—' }}</td>
                            <td>
                                @if ($document->state)
                                    <span class="badge bg-label-primary">{{ $document->state }}</span>
                                @elseif ($document->documentType?->is_state_specific)
                                    <span class="badge bg-label-warning">Missing</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
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
                                    @if (auth('admin')->user()?->can('admin.documents.upload'))
                                        <button type="button" wire:click.stop="openEditModal({{ $document->id }})"
                                            class="btn btn-outline-primary" title="Edit">
                                            <i class="ti tabler-edit pe-none"></i>
                                        </button>
                                    @endif
                                    @if (auth('admin')->user()?->can('admin.documents.verify'))
                                        @if ($vStatus !== 'verified')
                                            <button type="button" wire:click="verifyDocument({{ $document->id }})"
                                                class="btn btn-outline-success" title="Verify">
                                                <i class="ti tabler-check"></i>
                                            </button>
                                        @endif
                                        @if ($vStatus !== 'rejected')
                                            <button type="button" wire:click="openRejectModal({{ $document->id }})"
                                                class="btn btn-outline-danger" title="Reject">
                                                <i class="ti tabler-x"></i>
                                            </button>
                                        @endif
                                    @endif
                                    @if ($version)
                                        <a href="{{ route('admin.documents.download', $document) }}"
                                            class="btn btn-outline-secondary" title="Download">
                                            <i class="ti tabler-download"></i>
                                        </a>
                                    @endif
                                    @if (auth('admin')->user()?->can('admin.documents.upload'))
                                        <button type="button" wire:click="openVersionModal({{ $document->id }})"
                                            class="btn btn-outline-primary" title="New Version">
                                            <i class="ti tabler-versions"></i>
                                        </button>
                                    @endif
                                    @if (auth('admin')->user()?->can('admin.documents.delete'))
                                        <button type="button" wire:click="deleteDocument({{ $document->id }})"
                                            wire:confirm="Delete this document?" class="btn btn-outline-danger"
                                            title="Delete">
                                            <i class="ti tabler-trash"></i>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-5">
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
    @endif

    @if ($showModal)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,.5); z-index: 10850; position: fixed; inset: 0;">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ $modalMode === 'edit' ? 'Edit Document' : 'Upload Document' }}</h5>
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
                                    <select wire:model.live="formData.document_type_id" class="form-select @error('formData.document_type_id') is-invalid @enderror">
                                        <option value="">Select type...</option>
                                        @foreach ($documentTypes as $type)
                                            <option value="{{ $type->id }}">{{ $type->name }}{{ $type->is_state_specific ? ' (state-specific)' : '' }}</option>
                                        @endforeach
                                    </select>
                                    @error('formData.document_type_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">License / Certification / DEA number</label>
                                    <input type="text" wire:model="formData.document_number"
                                        class="form-control @error('formData.document_number') is-invalid @enderror"
                                        placeholder="License, certification, or DEA number">
                                    @error('formData.document_number')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Practice</label>
                                    <select wire:model="formData.practice_id" class="form-select @error('formData.practice_id') is-invalid @enderror">
                                        <option value="">None</option>
                                        @foreach ($practices as $practice)
                                            <option value="{{ $practice->id }}">{{ $practice->legal_name }}</option>
                                        @endforeach
                                    </select>
                                    @error('formData.practice_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Provider</label>
                                    <select wire:model.live="formData.provider_id" class="form-select @error('formData.provider_id') is-invalid @enderror">
                                        <option value="">None</option>
                                        @foreach ($providers as $provider)
                                            <option value="{{ $provider->id }}">
                                                {{ $provider->user->name ?? 'Provider #' . $provider->id }}</option>
                                        @endforeach
                                    </select>
                                    @error('formData.provider_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Credentialing Case</label>
                                    <select wire:model="formData.credentialing_case_id" class="form-select @error('formData.credentialing_case_id') is-invalid @enderror">
                                        <option value="">None</option>
                                        @foreach ($cases as $case)
                                            <option value="{{ $case->id }}">{{ $case->case_number }} —
                                                {{ $case->provider->user->name ?? '' }}</option>
                                        @endforeach
                                    </select>
                                    @error('formData.credentialing_case_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
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
                                    <label class="form-label">State @if($selectedType?->is_state_specific)<span class="text-danger">*</span>@endif</label>
                                    <select wire:model="formData.state" class="form-select @error('formData.state') is-invalid @enderror">
                                        <option value="">Select state...</option>
                                        @foreach ($states as $code => $name)
                                            <option value="{{ $code }}">{{ $code }} — {{ $name }}</option>
                                        @endforeach
                                    </select>
                                    @error('formData.state')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                    @if ($selectedType?->is_state_specific)
                                        <small class="text-muted">Required for {{ $selectedType->name }}. One current file per provider, type, and state.</small>
                                    @endif
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Visibility</label>
                                    <select wire:model.live="formData.visibility" class="form-select">
                                        <option value="standard">Standard (Scoped Staff)</option>
                                        <option value="restricted">Restricted (Role-gated)</option>
                                    </select>
                                </div>
                                <div class="col-md-4 d-flex align-items-end">
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" wire:model="formData.encrypt_at_rest" id="encryptAtRestCheck">
                                        <label class="form-check-label" for="encryptAtRestCheck">
                                            Encrypt file at rest (AES-256)
                                        </label>
                                    </div>
                                </div>
                                @if (($formData['visibility'] ?? 'standard') === 'restricted')
                                    <div class="col-12">
                                        <label class="form-label small fw-semibold">Allowed Roles for Restricted Document</label>
                                        <div class="d-flex flex-wrap gap-3">
                                            @foreach ($availableRoles as $roleOption)
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" value="{{ $roleOption->value }}"
                                                        wire:model="formData.allowed_roles" id="role-{{ $roleOption->value }}">
                                                    <label class="form-check-label small" for="role-{{ $roleOption->value }}">
                                                        {{ $roleOption->label() }}
                                                    </label>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                                @if ($selectedType?->is_state_specific)
                                    <div class="col-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" wire:model="formData.sync_credential" id="syncCredentialHub">
                                            <label class="form-check-label" for="syncCredentialHub">
                                                Create or update the matching provider credential for this state
                                            </label>
                                        </div>
                                    </div>
                                @endif
                                <div class="col-12">
                                    <label class="form-label">File @if ($modalMode !== 'edit')<span class="text-danger">*</span>@endif</label>
                                    <div x-data="{ dragging: false }"
                                        x-on:dragover.prevent="dragging = true"
                                        x-on:dragleave.prevent="dragging = false"
                                        x-on:drop.prevent="dragging = false; $refs.fileInput.files = $event.dataTransfer.files; $refs.fileInput.dispatchEvent(new Event('change', { bubbles: true }))"
                                        :class="dragging ? 'border-primary bg-light' : 'border-secondary-subtle'"
                                        class="border border-2 border-dashed rounded-3 p-3 text-center">
                                        <p class="small text-muted mb-2">
                                            <i class="ti tabler-cloud-upload me-1"></i>Drag &amp; drop file here, or click to browse
                                        </p>
                                        <input type="file" x-ref="fileInput" wire:model="uploadFile"
                                            class="form-control @error('uploadFile') is-invalid @enderror"
                                            accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                        @error('uploadFile')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div wire:loading wire:target="uploadFile" class="text-muted small mt-1">
                                        Uploading...</div>
                                    @if ($modalMode === 'edit')
                                        <div class="form-text">Leave empty to keep the current file. Upload a file to replace it.</div>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary"
                                wire:click="$set('showModal', false)">Cancel</button>
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="saveDocument">{{ $modalMode === 'edit' ? 'Save Changes' : 'Save Document' }}</span>
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
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,.5); z-index: 10850; position: fixed; inset: 0;">
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
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,.5); z-index: 10850; position: fixed; inset: 0;">
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
