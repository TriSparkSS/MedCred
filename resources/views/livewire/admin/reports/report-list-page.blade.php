<div class="container-fluid flex-grow-1 px-3 px-md-4 py-3 py-md-4">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-start gap-3">
            <div>
                <h3 class="fw-bold text-primary mb-2">Reports</h3>
                <p class="text-muted mb-0">Generate and export operational and compliance reports as CSV.</p>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <p class="text-uppercase text-muted small mb-1">Available Reports</p>
                    <h3 class="fw-bold mb-0">{{ $reportCount }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <p class="text-uppercase text-muted small mb-1">Export Format</p>
                    <h3 class="fw-bold mb-0">CSV</h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <p class="text-uppercase text-muted small mb-1">Last Generated</p>
                    <h3 class="fw-bold mb-0">{{ now()->format('M j') }}</h3>
                    <small class="text-muted">On demand</small>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <div class="row g-3 mb-3">
                <div class="col-md-8">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="ti tabler-search"></i></span>
                        <input type="search" wire:model.live.debounce.300ms="search" class="form-control border-start-0"
                            placeholder="Search reports by name or type...">
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Report Name</th>
                            <th>Type</th>
                            <th>Description</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($reports as $key => $report)
                            <tr wire:key="report-{{ $key }}">
                                <td class="fw-semibold">{{ $report['name'] }}</td>
                                <td><span class="badge bg-label-primary">{{ $report['type'] }}</span></td>
                                <td class="text-muted small">{{ $report['description'] }}</td>
                                <td class="text-end">
                                    <a href="{{ route('admin.reports.export', $key) }}"
                                        class="btn btn-sm btn-primary">
                                        <i class="ti tabler-download me-1"></i>Download CSV
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">No reports match your search.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <h6 class="fw-semibold mb-3">Report Guide</h6>
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="border rounded-3 p-3 h-100">
                        <p class="fw-semibold mb-1">Open Applications</p>
                        <small class="text-muted">All active credentialing cases with status, payer, state, and assigned owner.</small>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="border rounded-3 p-3 h-100">
                        <p class="fw-semibold mb-1">Document Compliance</p>
                        <small class="text-muted">Checklist completion % per case based on payer document requirements.</small>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="border rounded-3 p-3 h-100">
                        <p class="fw-semibold mb-1">Case Aging</p>
                        <small class="text-muted">Days in process for each active application from intake or submission date.</small>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="border rounded-3 p-3 h-100">
                        <p class="fw-semibold mb-1">Expiring Documents</p>
                        <small class="text-muted">Documents expiring within the next 30 days across all providers and cases.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
