<div class="container-fluid flex-grow-1 px-3 px-md-4 py-3 py-md-4">

    <div class="row g-4 mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-start gap-3">
                    <div>
                        <h3 class="fw-bold text-primary mb-2">Executive Dashboard</h3>
                        <p class="text-muted mb-0">Real-time status of credentialing operations and compliance.</p>
                    </div>
                    <div class="d-flex flex-column flex-sm-row gap-2">
                        <a href="{{ route('admin.credentials.create') }}" class="btn btn-primary">
                            <i class="ti tabler-file-plus me-1"></i> New Application
                        </a>
                        <a href="{{ route('admin.reports') }}" class="btn btn-outline-secondary">
                            <i class="ti tabler-download me-1"></i> Export Report
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-md-4 col-xl-2">
            <a href="{{ route('admin.providers') }}" class="text-decoration-none">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body">
                        <div class="text-uppercase text-muted fw-semibold small mb-2">Active Providers</div>
                        <h2 class="fw-bold mb-1 text-dark">{{ number_format($stats['active_providers']) }}</h2>
                        <p class="mb-0 text-muted small">Approved & credentialed</p>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-sm-6 col-md-4 col-xl-2">
            <a href="{{ route('admin.credentials') }}" class="text-decoration-none">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <div class="text-uppercase text-muted fw-semibold small">Apps In Progress</div>
                            @if ($stats['rush_cases'] > 0)
                                <span class="badge bg-label-warning">{{ $stats['rush_cases'] }} Rush</span>
                            @endif
                        </div>
                        <h2 class="fw-bold mb-1 text-dark">{{ $stats['apps_in_progress'] }}</h2>
                        <p class="mb-0 text-muted small">Active credentialing cases</p>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-sm-6 col-md-4 col-xl-2">
            <a href="{{ route('admin.credentials', ['category' => 'payer']) }}" class="text-decoration-none">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body">
                        <div class="text-uppercase text-muted fw-semibold small mb-2">Pending With Payer</div>
                        <h2 class="fw-bold mb-1 text-dark">{{ $stats['pending_payer'] }}</h2>
                        <p class="mb-0 text-muted small">Awaiting payer response</p>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-sm-6 col-md-4 col-xl-2">
            <a href="{{ route('admin.credentials', ['category' => 'provider']) }}" class="text-decoration-none">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body">
                        <div class="text-uppercase text-muted fw-semibold small mb-2">Pending With Provider</div>
                        <h2 class="fw-bold mb-1 text-dark">{{ $stats['pending_provider'] }}</h2>
                        <p class="mb-0 text-muted small">Provider document follow-up</p>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-sm-6 col-md-4 col-xl-2">
            <a href="{{ route('admin.credentials', ['category' => 'overdue']) }}" class="text-decoration-none">
                <div class="card h-100 shadow-sm border-0 border-danger">
                    <div class="card-body">
                        <div class="text-uppercase text-muted fw-semibold small mb-2">Overdue Follow-Ups</div>
                        <h2 class="fw-bold text-danger mb-1">{{ $stats['overdue_followups'] }}</h2>
                        <p class="mb-0 text-muted small">Cases & tasks past due</p>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-sm-6 col-md-4 col-xl-2">
            <a href="{{ route('admin.documents', ['filterExpiry' => 'expiring']) }}" class="text-decoration-none">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body">
                        <div class="text-uppercase text-muted fw-semibold small mb-2">Expiring (30 Days)</div>
                        <h2 class="fw-bold mb-1 text-dark">{{ $stats['expiring_documents'] }}</h2>
                        <p class="mb-0 text-muted small">Documents needing renewal</p>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-7">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-header d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 bg-white">
                    <div>
                        <h5 class="mb-1">Today's Action Items</h5>
                        <small class="text-muted">Overdue and due-today tasks and case follow-ups.</small>
                    </div>
                    <span class="badge bg-label-secondary">{{ $workQueue->count() }} ITEMS</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Priority</th>
                                <th>Item</th>
                                <th>Provider</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($workQueue as $item)
                                <tr wire:key="queue-{{ $item['type'] }}-{{ $item['id'] }}">
                                    <td>
                                        <span class="badge rounded-pill bg-{{ $item['priority_class'] }}">●</span>
                                    </td>
                                    <td class="fw-medium">{{ $item['title'] }}</td>
                                    <td>{{ $item['provider'] }}</td>
                                    <td>
                                        <span class="badge bg-label-{{ $item['status_class'] }} text-uppercase">{{ $item['status'] }}</span>
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ $item['url'] }}" class="btn btn-sm btn-primary">Open</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">No urgent items — you're caught up.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Delay Ownership</h5>
                    <small class="text-muted">Active cases by delay owner</small>
                </div>
                <div class="card-body">
                    <div id="delayOwnershipChart" style="min-height: 280px;"></div>
                    <div class="mt-3">
                        @foreach ($delayBreakdown['items'] as $item)
                            <div class="d-flex justify-content-between align-items-center mb-2 small">
                                <span>{{ $item['name'] }}</span>
                                <strong>{{ $item['percent'] }}% ({{ $item['count'] }})</strong>
                            </div>
                        @endforeach
                        @if (empty($delayBreakdown['items']))
                            <p class="text-muted small mb-0">No active cases to display.</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 bg-white">
                    <div>
                        <h5 class="mb-1">Recent Activity Feed</h5>
                        <small class="text-muted">Latest credentialing events and updates.</small>
                    </div>
                    <a href="{{ route('admin.credentials') }}" class="text-decoration-none small">View All Cases</a>
                </div>
                <div class="card-body">
                    @if ($recentActivity->isEmpty())
                        <p class="text-muted text-center py-4 mb-0">No activity recorded yet.</p>
                    @else
                        <div class="row g-3">
                            @foreach ($recentActivity->take(8) as $activity)
                                @php
                                    $icon = match ($activity->activity_type) {
                                        'status_change' => ['tabler-refresh', 'info'],
                                        'note' => ['tabler-note', 'primary'],
                                        'call' => ['tabler-phone', 'success'],
                                        default => ['tabler-activity', 'secondary'],
                                    };
                                @endphp
                                <div class="col-md-6 col-lg-3" wire:key="activity-{{ $activity->id }}">
                                    <div class="border rounded-3 p-3 h-100">
                                        <div class="mb-2">
                                            <i class="ti {{ $icon[0] }} fs-3 text-{{ $icon[1] }}"></i>
                                        </div>
                                        <h6 class="mb-1">{{ ucfirst(str_replace('_', ' ', $activity->activity_type)) }}</h6>
                                        <p class="small mb-1">{{ Str::limit($activity->summary, 60) }}</p>
                                        <small class="text-muted d-block">
                                            {{ $activity->credentialingCase->payer->name ?? '' }}
                                            @if ($activity->credentialingCase?->provider?->user)
                                                · {{ $activity->credentialingCase->provider->user->name }}
                                            @endif
                                        </small>
                                        <small class="text-muted">{{ $activity->created_at->diffForHumans() }}</small>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
    <script src="{{ asset('assets/vendor/libs/apex-charts/apexcharts.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var chartEl = document.querySelector('#delayOwnershipChart');
            if (!chartEl || typeof ApexCharts === 'undefined') return;

            var options = {
                series: @json($delayBreakdown['series']),
                chart: { type: 'donut', height: 280 },
                labels: @json($delayBreakdown['labels']),
                colors: ['#091572', '#f39c12', '#5dade2', '#28c76f', '#ff4c51', '#808390'],
                legend: { show: false },
                dataLabels: { enabled: true },
            };

            new ApexCharts(chartEl, options).render();
        });
    </script>
@endpush
