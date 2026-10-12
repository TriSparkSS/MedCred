@php
    $licenseDocuments = $practice->documents ?? collect();
    $showDocumentsTable = $showDocumentsTable ?? true;
    $showManageButton = $showManageButton ?? false;
@endphp

<div class="card shadow-sm border-0 {{ $class ?? '' }}" data-section="license-documents">
    <div class="card-header bg-white border-bottom d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2">
        <div>
            <h6 class="mb-0 fw-semibold">
                <i class="ti tabler-certificate me-2"></i>License &amp; Documents
            </h6>
            <small class="text-muted">Each document includes a name, number, effective date, and expiration.</small>
        </div>
        @if ($showManageButton)
            <button type="button" class="btn btn-sm btn-outline-primary" wire:click="setTab('documents')">
                Manage documents
            </button>
        @endif
    </div>
    <div class="card-body">
        <div class="row g-4">
            <div class="col-md-3">
                <small class="text-muted d-block mb-1">License / document name</small>
                <span class="fw-semibold">{{ $practice->license_name ?: 'N/A' }}</span>
            </div>
            <div class="col-md-3">
                <small class="text-muted d-block mb-1">License Number</small>
                <span class="fw-semibold">{{ $practice->license_number ?: 'N/A' }}</span>
            </div>
            <div class="col-md-3">
                <small class="text-muted d-block mb-1">Effective date</small>
                <span class="fw-semibold">{{ $practice->license_effective_date?->format('m/d/Y') ?: 'N/A' }}</span>
            </div>
            <div class="col-md-3">
                <small class="text-muted d-block mb-1">Expiration</small>
                <span class="fw-semibold">{{ $practice->license_expiry_date?->format('m/d/Y') ?: 'N/A' }}</span>
            </div>
            @if ($practice->document_path)
                <div class="col-md-4">
                    <small class="text-muted d-block mb-1">Uploaded Document</small>
                    <a href="{{ asset('storage/' . $practice->document_path) }}" target="_blank" class="fw-semibold">
                        <i class="ti tabler-file me-1"></i>{{ $practice->document_original_name ?: 'View file' }}
                    </a>
                </div>
            @endif
        </div>

        @if ($showDocumentsTable)
            <div class="table-responsive mt-4">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Document</th>
                            <th>Number</th>
                            <th>Effective</th>
                            <th>Expiration</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($licenseDocuments as $document)
                            <tr>
                                <td class="fw-semibold">{{ $document->title }}</td>
                                <td>{{ $document->document_number ?: '—' }}</td>
                                <td>{{ $document->effective_date?->format('m/d/Y') ?: '—' }}</td>
                                <td>{{ $document->expiry_date?->format('m/d/Y') ?: '—' }}</td>
                            </tr>
                        @empty
                            @if ($practice->license_name || $practice->license_number || $practice->document_path)
                                <tr>
                                    <td class="fw-semibold">{{ $practice->license_name ?: ($practice->document_original_name ?: 'Practice License') }}</td>
                                    <td>{{ $practice->license_number ?: '—' }}</td>
                                    <td>{{ $practice->license_effective_date?->format('m/d/Y') ?: '—' }}</td>
                                    <td>{{ $practice->license_expiry_date?->format('m/d/Y') ?: '—' }}</td>
                                </tr>
                            @else
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">No license or documents on file for this practice.</td>
                                </tr>
                            @endif
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
