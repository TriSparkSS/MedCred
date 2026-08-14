<div>
    <div class="container-fluid flex-grow-1 px-3 px-md-4 py-3 py-md-4">
        <x-practice.page-header
            title="Upload Center"
            subtitle="Submit requested credentialing documents for your practice applications."
        />

        <div class="row g-4">
            @if($canUpload ?? true)
            <div class="col-lg-5">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white border-bottom">
                        <h6 class="fw-semibold mb-0"><i class="ti tabler-upload me-2 text-primary"></i>Upload Document</h6>
                    </div>
                    <div class="card-body">
                        <form wire:submit.prevent="saveUpload" class="row g-3">
                            <div class="col-12">
                                <label class="form-label fw-medium">Application</label>
                                <select wire:model="selectedCaseId" class="form-select @error('selectedCaseId') is-invalid @enderror">
                                    <option value="">Select case...</option>
                                    @foreach($cases as $case)
                                        <option value="{{ $case->id }}">{{ $case->case_number }} — {{ $case->provider->user->name ?? '' }} · {{ $case->payer->name ?? '' }}</option>
                                    @endforeach
                                </select>
                                @error('selectedCaseId')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-medium">Document Type</label>
                                <select wire:model="selectedDocumentTypeId" class="form-select">
                                    <option value="">Select type...</option>
                                    @foreach($documentTypes as $type)
                                        <option value="{{ $type->id }}">{{ $type->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-medium">Title</label>
                                <input type="text" wire:model="title" class="form-control @error('title') is-invalid @enderror">
                                @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-medium">File (PDF, DOC, JPG — max 10MB)</label>
                                <input type="file" wire:model="uploadFile" class="form-control @error('uploadFile') is-invalid @enderror" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                @error('uploadFile')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary w-100" wire:loading.attr="disabled">
                                    <span wire:loading.remove wire:target="saveUpload">Upload Document</span>
                                    <span wire:loading wire:target="saveUpload">Uploading...</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            @endif
            <div class="{{ ($canUpload ?? true) ? 'col-lg-7' : 'col-12' }}">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white">
                        <h6 class="fw-semibold mb-0">Outstanding Requests</h6>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr><th>Document</th><th>Case</th><th>Provider</th><th>Payer</th></tr>
                            </thead>
                            <tbody>
                                @forelse($outstanding as $item)
                                    <tr>
                                        <td><span class="badge bg-label-warning">{{ $item['document_type'] }}</span></td>
                                        <td class="fw-semibold">{{ $item['case_number'] }}</td>
                                        <td>{{ $item['provider'] }}</td>
                                        <td>{{ $item['payer'] }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-4">No outstanding document requests.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white">
                        <h6 class="fw-semibold mb-0">Practice Uploads</h6>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr><th>Title</th><th>Type</th></tr>
                            </thead>
                            <tbody>
                                @forelse($myDocuments as $doc)
                                    <tr>
                                        <td class="fw-semibold">{{ $doc->title }}</td>
                                        <td>{{ $doc->documentType->name ?? '—' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="2" class="text-center text-muted py-4">No uploads yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
