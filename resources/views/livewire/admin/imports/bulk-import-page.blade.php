<div class="container-fluid flex-grow-1 px-3 px-md-4 py-3 py-md-4">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <h3 class="fw-bold text-primary mb-1">Bulk Excel Import</h3>
            <p class="text-muted mb-0">
                Create new facilities (practices) and providers from Excel or CSV. Each row is a new record;
                duplicate email, NPI, or client code fails only that row. Download a template, fill it, then upload.
            </p>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                        <div>
                            <h5 class="mb-1">Facilities (Practices)</h5>
                            <p class="text-muted small mb-0">
                                Required: <code>legal_name</code>, <code>client_code</code>, <code>email</code>,
                                <code>address1</code>, <code>city</code>, <code>state</code>, <code>zip_code</code>.
                                Optional: {{ implode(', ', array_diff($practiceColumns, ['legal_name', 'client_code', 'email', 'address1', 'city', 'state', 'zip_code'])) }}.
                            </p>
                        </div>
                        <button type="button" wire:click="downloadPracticeTemplate" class="btn btn-sm btn-outline-primary text-nowrap">
                            <i class="ti tabler-download me-1"></i>Template
                        </button>
                    </div>
                    <form wire:submit.prevent="importPractices" class="row g-2 align-items-end">
                        <div class="col">
                            <label class="form-label">Excel or CSV</label>
                            <input type="file" wire:model="practiceFile"
                                class="form-control @error('practiceFile') is-invalid @enderror"
                                accept=".xlsx,.xls,.csv,.txt">
                            @error('practiceFile')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="importPractices">
                                Import Practices
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                        <div>
                            <h5 class="mb-1">Providers</h5>
                            <p class="text-muted small mb-0">
                                Required: <code>name</code>, <code>email</code>, <code>npi</code>,
                                <code>practice</code>, <code>address</code>, <code>city</code>, <code>state</code>, <code>zip</code>.
                                Optional: {{ implode(', ', array_diff($providerColumns, ['name', 'email', 'npi', 'practice', 'address', 'city', 'state', 'zip'])) }}.
                            </p>
                        </div>
                        <button type="button" wire:click="downloadProviderTemplate" class="btn btn-sm btn-outline-primary text-nowrap">
                            <i class="ti tabler-download me-1"></i>Template
                        </button>
                    </div>
                    <form wire:submit.prevent="importProviders" class="row g-2 align-items-end">
                        <div class="col">
                            <label class="form-label">Excel or CSV</label>
                            <input type="file" wire:model="providerFile"
                                class="form-control @error('providerFile') is-invalid @enderror"
                                accept=".xlsx,.xls,.csv,.txt">
                            @error('providerFile')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="importProviders">
                                Import Providers
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @if ($lastBatch)
        <div class="alert {{ $lastBatch->failed_rows ? 'alert-warning' : 'alert-info' }} mb-4">
            Last import ({{ $lastBatch->type }}): {{ $lastBatch->success_rows }}/{{ $lastBatch->total_rows }} rows succeeded.
            @if ($lastBatch->errors)
                <ul class="mb-0 mt-2 small">
                    @foreach (array_slice($lastBatch->errors, 0, 10) as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white">
            <h6 class="mb-0">Recent Imports</h6>
        </div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead class="table-light">
                    <tr>
                        <th>File</th>
                        <th>Type</th>
                        <th>Success</th>
                        <th>Failed</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($batches as $batch)
                        <tr>
                            <td>{{ $batch->filename }}</td>
                            <td>{{ $batch->type }}</td>
                            <td>{{ $batch->success_rows }}</td>
                            <td>{{ $batch->failed_rows }}</td>
                            <td>{{ $batch->created_at->format('m/d/Y g:i A') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-3">No imports yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
