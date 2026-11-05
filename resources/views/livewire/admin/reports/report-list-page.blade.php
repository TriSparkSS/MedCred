<div class="container-fluid flex-grow-1 px-3 px-md-4 py-3 py-md-4">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-start gap-3">
            <div>
                <h3 class="fw-bold text-primary mb-2">Reports</h3>
                <p class="text-muted mb-0">View interactive operational, compliance, and analytics reports on screen or export as CSV, Excel, or PDF.</p>
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
                    <h3 class="fw-bold mb-0">Screen, CSV, Excel &amp; PDF</h3>
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

    {{-- Approval, Denial & Panel-Closed Summary KPI Strip (BRD #14) --}}
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
                <div>
                    <h5 class="fw-bold mb-1">Approval, Denial &amp; Panel-Closed Summary</h5>
                    <p class="text-muted small mb-0">Real-time outcome metrics across {{ $approvalSummary['total_cases'] ?? 0 }} total credentialing applications.</p>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" wire:click="viewReport('approval_denial_trends')" class="btn btn-sm btn-outline-primary">
                        <i class="ti tabler-chart-bar me-1"></i>Approval &amp; Denial Trends
                    </button>
                    <button type="button" wire:click="viewReport('panel_closed_summary')" class="btn btn-sm btn-outline-secondary">
                        <i class="ti tabler-ban me-1"></i>Panel-Closed Report
                    </button>
                </div>
            </div>
            <div class="row g-3">
                <div class="col-6 col-md-3">
                    <div class="border rounded-3 p-3 h-100 bg-light-subtle">
                        <p class="text-uppercase text-muted small mb-1">Approval Rate %</p>
                        <h4 class="fw-bold text-primary mb-0">{{ $approvalSummary['approval_rate_percent'] ?? 0 }}%</h4>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="border rounded-3 p-3 h-100 bg-light-subtle">
                        <p class="text-uppercase text-muted small mb-1">Approved</p>
                        <h4 class="fw-bold text-success mb-0">{{ $approvalSummary['approved_count'] ?? 0 }}</h4>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="border rounded-3 p-3 h-100 bg-light-subtle">
                        <p class="text-uppercase text-muted small mb-1">Denied / Rejected</p>
                        <h4 class="fw-bold text-danger mb-0">{{ $approvalSummary['denied_count'] ?? 0 }}</h4>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="border rounded-3 p-3 h-100 bg-light-subtle">
                        <p class="text-uppercase text-muted small mb-1">Panel Closed</p>
                        <h4 class="fw-bold text-warning mb-0">{{ $approvalSummary['panel_closed_count'] ?? 0 }}</h4>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Management Delay Breakdown Section (BRD #13) --}}
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-3">
                <div>
                    <h5 class="fw-bold mb-1">Management Delay Breakdown</h5>
                    <p class="text-muted small mb-0">Share of {{ $delayBreakdown['total_active'] ?? 0 }} active delayed cases and average aging days by owner, executive, provider, and payer.</p>
                </div>
                <div class="btn-group btn-group-sm" role="group" aria-label="Delay Dimension Tabs">
                    <button type="button" wire:click="setDelayDimension('by_owner')"
                        class="btn {{ $delayDimension === 'by_owner' ? 'btn-primary' : 'btn-outline-primary' }}">
                        By Delay Owner / Team
                    </button>
                    <button type="button" wire:click="setDelayDimension('by_team')"
                        class="btn {{ $delayDimension === 'by_team' ? 'btn-primary' : 'btn-outline-primary' }}">
                        By Executive
                    </button>
                    <button type="button" wire:click="setDelayDimension('by_provider')"
                        class="btn {{ $delayDimension === 'by_provider' ? 'btn-primary' : 'btn-outline-primary' }}">
                        By Provider
                    </button>
                    <button type="button" wire:click="setDelayDimension('by_payer')"
                        class="btn {{ $delayDimension === 'by_payer' ? 'btn-primary' : 'btn-outline-primary' }}">
                        By Payer
                    </button>
                    <button type="button" wire:click="setDelayDimension('by_practice')"
                        class="btn {{ $delayDimension === 'by_practice' ? 'btn-primary' : 'btn-outline-primary' }}">
                        By Practice
                    </button>
                </div>
            </div>

            @php
                $currentDelayRows = $delayBreakdown[$delayDimension] ?? [];
            @endphp
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Dimension</th>
                            <th class="text-end">Active Cases</th>
                            <th style="min-width: 220px;">Share of Delayed Cases</th>
                            <th class="text-end">Avg Waiting Days</th>
                            <th class="text-end">Avg Aging Days</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($currentDelayRows as $item)
                            @php
                                $avgAging = (int) ($item['avg_aging_days'] ?? 0);
                                $avgWaiting = (int) ($item['avg_waiting_days'] ?? $avgAging);
                                $agingBadgeClass = $avgAging >= 60
                                    ? 'bg-label-danger'
                                    : ($avgAging >= 30 ? 'bg-label-warning' : 'bg-label-success');
                                $waitingBadgeClass = $avgWaiting >= 60
                                    ? 'bg-label-danger'
                                    : ($avgWaiting >= 30 ? 'bg-label-warning' : 'bg-label-info');
                                $barColorClass = $avgAging >= 60
                                    ? 'bg-danger'
                                    : ($avgAging >= 30 ? 'bg-warning' : 'bg-primary');
                            @endphp
                            <tr>
                                <td class="fw-semibold">{{ $item['label'] }}</td>
                                <td class="text-end fw-semibold">{{ $item['count'] }}</td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress flex-grow-1" style="height: 8px;">
                                            <div class="progress-bar {{ $barColorClass }}" role="progressbar"
                                                style="width: {{ min(100, (float) $item['percent']) }}%"
                                                aria-valuenow="{{ $item['percent'] }}" aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                        <span class="small text-muted text-nowrap" style="min-width: 48px;">{{ $item['percent'] }}%</span>
                                    </div>
                                </td>
                                <td class="text-end">
                                    <span class="badge {{ $waitingBadgeClass }}">{{ $avgWaiting }} days</span>
                                </td>
                                <td class="text-end">
                                    <span class="badge {{ $agingBadgeClass }}">{{ $avgAging }} days</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-3">No active delayed cases found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Interactive Report Screen Card (BRD #15) --}}
    @if ($activeReport && $activeReportData && $activeReportMeta)
        <div class="card shadow-sm border-primary mb-4" id="interactive-report-screen">
            <div class="card-header bg-white d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 py-3">
                <div>
                    <div class="d-flex align-items-center gap-2">
                        <h5 class="fw-bold text-primary mb-0">{{ $activeReportMeta['name'] }}</h5>
                        <span class="badge bg-label-primary">{{ count($activeReportData['rows']) }} rows</span>
                    </div>
                    <p class="text-muted small mb-0">{{ $activeReportMeta['description'] }}</p>
                </div>
                <div class="d-flex align-items-center gap-2">
                    @if ($canExport ?? false)
                        <a href="{{ route('admin.reports.export', ['type' => $activeReport, 'format' => 'csv']) }}" class="btn btn-sm btn-primary">
                            <i class="ti tabler-download me-1"></i>Download CSV
                        </a>
                        <a href="{{ route('admin.reports.export', ['type' => $activeReport, 'format' => 'xlsx']) }}" class="btn btn-sm btn-outline-success">
                            <i class="ti tabler-file-spreadsheet me-1"></i>Excel
                        </a>
                        <a href="{{ route('admin.reports.export', ['type' => $activeReport, 'format' => 'pdf']) }}" class="btn btn-sm btn-outline-danger">
                            <i class="ti tabler-file-type-pdf me-1"></i>PDF
                        </a>
                    @endif
                    <button type="button" wire:click="closeReport" class="btn btn-sm btn-outline-secondary">
                        <i class="ti tabler-x me-1"></i>Close Screen
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 520px;">
                    <table class="table table-hover table-striped align-middle mb-0">
                        <thead class="table-light sticky-top">
                            <tr>
                                @foreach ($activeReportData['headers'] as $header)
                                    <th class="text-nowrap">{{ $header }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($activeReportData['rows'] as $rowIndex => $row)
                                <tr wire:key="active-report-row-{{ $rowIndex }}">
                                    @foreach ($row as $cell)
                                        <td>{{ $cell }}</td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ count($activeReportData['headers']) }}" class="text-center text-muted py-4">
                                        No records found for this report.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

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
                            <tr wire:key="report-{{ $key }}" @class(['table-active' => $activeReport === $key])>
                                <td class="fw-semibold">{{ $report['name'] }}</td>
                                <td><span class="badge bg-label-primary">{{ $report['type'] }}</span></td>
                                <td class="text-muted small">{{ $report['description'] }}</td>
                                <td class="text-end text-nowrap">
                                    <div class="d-inline-flex align-items-center gap-1">
                                        <button type="button" wire:click="viewReport('{{ $key }}')"
                                            class="btn btn-sm {{ $activeReport === $key ? 'btn-primary' : 'btn-outline-primary' }}">
                                            <i class="ti tabler-eye me-1"></i>View Screen
                                        </button>
                                        @if ($canExport ?? false)
                                            <a href="{{ route('admin.reports.export', ['type' => $key, 'format' => 'csv']) }}"
                                                class="btn btn-sm btn-primary">
                                                <i class="ti tabler-download me-1"></i>Download CSV
                                            </a>
                                            <a href="{{ route('admin.reports.export', ['type' => $key, 'format' => 'xlsx']) }}"
                                                class="btn btn-sm btn-outline-success">
                                                <i class="ti tabler-file-spreadsheet me-1"></i>Excel
                                            </a>
                                            <a href="{{ route('admin.reports.export', ['type' => $key, 'format' => 'pdf']) }}"
                                                class="btn btn-sm btn-outline-danger">
                                                <i class="ti tabler-file-type-pdf me-1"></i>PDF
                                            </a>
                                        @endif
                                        @if (!empty($report['link']))
                                            <a href="{{ $report['link'] }}" class="btn btn-sm btn-outline-secondary">
                                                <i class="ti tabler-external-link me-1"></i>Open
                                            </a>
                                        @endif
                                    </div>
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
                        <p class="fw-semibold mb-1">Total Credentialing Report by Practice</p>
                        <small class="text-muted">Practice → provider → payer status with filters, KPIs, email, and Excel download.</small>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="border rounded-3 p-3 h-100">
                        <p class="fw-semibold mb-1">Open Applications</p>
                        <small class="text-muted">All active credentialing cases with status, payer, state, and assigned owner.</small>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="border rounded-3 p-3 h-100">
                        <p class="fw-semibold mb-1">Approval &amp; Denial Trends / Panel-Closed Report</p>
                        <small class="text-muted">Approval rates, denied counts, and panel-closed breakdown by payer, state, and specialty.</small>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="border rounded-3 p-3 h-100">
                        <p class="fw-semibold mb-1">Delay Ownership &amp; Management Breakdown</p>
                        <small class="text-muted">Share of delayed cases across Provider/Practice, Revantage Team, Payer, executives, and providers.</small>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="border rounded-3 p-3 h-100">
                        <p class="fw-semibold mb-1">Document Compliance &amp; Expiring Documents</p>
                        <small class="text-muted">Checklist completion % per case and documents expiring within the next 30 days.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
