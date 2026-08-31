<div class="container-fluid flex-grow-1 px-3 px-md-4 py-3 py-md-4 practice-cred-report">
    <style>
        .practice-cred-report {
            --pcr-navy: #2f3a6d;
            --pcr-navy-deep: #253057;
            --pcr-text: #2f2b3d;
            --pcr-muted: #8a8797;
            --pcr-border: #e7e7ef;
            --pcr-surface: #ffffff;
            --pcr-soft: #f5f5f9;
            --pcr-success: #28c76f;
            --pcr-primary: #7367f0;
            --pcr-warning: #ff9f43;
            --pcr-info: #9b7bff;
            --pcr-danger: #ea5455;
            --pcr-secondary: #a8aaae;
        }

        .practice-cred-report .pcr-shell {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .practice-cred-report .pcr-header {
            background: var(--pcr-surface);
            border: 1px solid var(--pcr-border);
            border-radius: .85rem;
            padding: 1rem 1.25rem 1.15rem;
            box-shadow: 0 2px 8px rgba(47, 58, 109, .04);
        }

        .practice-cred-report .pcr-back {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            color: var(--pcr-navy);
            font-size: .8125rem;
            font-weight: 600;
            text-decoration: none;
            margin-bottom: .75rem;
        }

        .practice-cred-report .pcr-back:hover {
            color: var(--pcr-navy-deep);
        }

        .practice-cred-report .pcr-header-main {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 1rem 1.5rem;
            align-items: flex-start;
        }

        .practice-cred-report .pcr-title {
            color: var(--pcr-navy);
            font-weight: 700;
            font-size: 1.5rem;
            letter-spacing: -.01em;
            margin: 0 0 .35rem;
            line-height: 1.25;
        }

        .practice-cred-report .pcr-subtitle {
            color: var(--pcr-muted);
            font-size: .9rem;
            margin: 0;
            max-width: 42rem;
        }

        .practice-cred-report .pcr-meta {
            display: flex;
            flex-direction: column;
            gap: .4rem;
            min-width: 12rem;
        }

        .practice-cred-report .pcr-meta-item {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: .5rem;
            color: var(--pcr-text);
            font-size: .8125rem;
            white-space: nowrap;
        }

        .practice-cred-report .pcr-meta-item span {
            color: var(--pcr-muted);
            font-weight: 500;
        }

        .practice-cred-report .pcr-meta-item strong {
            font-weight: 600;
        }

        .practice-cred-report .pcr-panel {
            background: var(--pcr-surface);
            border: 1px solid var(--pcr-border);
            border-radius: .85rem;
            box-shadow: 0 2px 8px rgba(47, 58, 109, .04);
        }

        .practice-cred-report .pcr-filters {
            padding: 1rem 1.15rem;
        }

        .practice-cred-report .pcr-filters-label {
            font-size: .72rem;
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
            color: var(--pcr-muted);
            margin-bottom: .75rem;
        }

        .practice-cred-report .pcr-filters .form-label {
            font-size: .75rem;
            font-weight: 600;
            color: var(--pcr-muted);
            margin-bottom: .35rem;
        }

        .practice-cred-report .pcr-filters .form-control,
        .practice-cred-report .pcr-filters .form-select {
            border-color: var(--pcr-border);
            font-size: .875rem;
            min-height: 2.4rem;
            box-shadow: none;
        }

        .practice-cred-report .pcr-filters .form-control:focus,
        .practice-cred-report .pcr-filters .form-select:focus {
            border-color: #b8bfd9;
            box-shadow: 0 0 0 .2rem rgba(47, 58, 109, .08);
        }

        .practice-cred-report .pcr-actions {
            display: flex;
            flex-wrap: wrap;
            gap: .5rem;
            align-items: center;
        }

        .practice-cred-report .btn-pcr-primary {
            background: var(--pcr-navy);
            border-color: var(--pcr-navy);
            color: #fff;
            font-weight: 600;
            padding: .55rem 1.1rem;
            box-shadow: 0 2px 6px rgba(47, 58, 109, .18);
        }

        .practice-cred-report .btn-pcr-primary:hover {
            background: var(--pcr-navy-deep);
            border-color: var(--pcr-navy-deep);
            color: #fff;
        }

        .practice-cred-report .btn-pcr-ghost {
            border-color: var(--pcr-border);
            color: var(--pcr-text);
            background: #fff;
            font-weight: 500;
        }

        .practice-cred-report .btn-pcr-ghost:hover {
            background: var(--pcr-soft);
            border-color: #d4d5e0;
            color: var(--pcr-navy);
        }

        .practice-cred-report .pcr-kpis {
            overflow-x: auto;
        }

        .practice-cred-report .pcr-kpi-row {
            display: flex;
            min-width: 1080px;
        }

        .practice-cred-report .pcr-kpi {
            flex: 1 1 0;
            padding: 1rem 1.05rem;
            border-right: 1px solid var(--pcr-border);
            min-width: 0;
        }

        .practice-cred-report .pcr-kpi:last-child {
            border-right: 0;
        }

        .practice-cred-report .pcr-kpi.is-divider {
            position: relative;
        }

        .practice-cred-report .pcr-kpi.is-divider::after {
            content: '';
            position: absolute;
            top: 18%;
            right: 0;
            width: 2px;
            height: 64%;
            background: #dfe1ea;
            border-radius: 2px;
        }

        .practice-cred-report .pcr-kpi-label {
            color: var(--pcr-muted);
            font-size: .7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .03em;
            margin-bottom: .4rem;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: .4rem;
        }

        .practice-cred-report .pcr-kpi-value {
            font-weight: 700;
            font-size: 1.2rem;
            color: var(--pcr-text);
            line-height: 1.2;
            letter-spacing: -.01em;
        }

        .practice-cred-report .pcr-kpi-pct {
            color: var(--pcr-muted);
            font-size: .78rem;
            font-weight: 500;
            margin-left: .15rem;
        }

        .practice-cred-report .pcr-dot {
            width: .55rem;
            height: .55rem;
            border-radius: 50%;
            display: inline-block;
            flex-shrink: 0;
            box-shadow: inset 0 0 0 1px rgba(0, 0, 0, .04);
        }

        .practice-cred-report .pcr-dot.success { background: var(--pcr-success); }
        .practice-cred-report .pcr-dot.primary { background: #4c6fff; }
        .practice-cred-report .pcr-dot.warning { background: var(--pcr-warning); }
        .practice-cred-report .pcr-dot.info { background: var(--pcr-info); }
        .practice-cred-report .pcr-dot.danger { background: var(--pcr-danger); }
        .practice-cred-report .pcr-dot.secondary { background: var(--pcr-secondary); }

        .practice-cred-report .pcr-table-wrap {
            overflow: hidden;
        }

        .practice-cred-report .pcr-table {
            margin-bottom: 0;
        }

        .practice-cred-report .pcr-table thead th {
            background: var(--pcr-navy) !important;
            color: #fff !important;
            border: 0 !important;
            font-size: .75rem;
            font-weight: 600;
            letter-spacing: .02em;
            text-transform: uppercase;
            white-space: nowrap;
            vertical-align: middle;
            padding: .85rem 1rem;
        }

        .practice-cred-report .pcr-table tbody td {
            padding: .7rem 1rem;
            border-color: #efeff5;
            color: var(--pcr-text);
            font-size: .875rem;
            vertical-align: middle;
        }

        .practice-cred-report .pcr-table tbody tr.pcr-data-row:hover td {
            background: #fafafc;
        }

        .practice-cred-report .pcr-group-row td {
            background: var(--pcr-soft) !important;
            font-weight: 600;
            color: var(--pcr-text);
            border-top: 1px solid #e3e4ec !important;
            border-bottom: 1px solid #e3e4ec !important;
            padding-top: .75rem;
            padding-bottom: .75rem;
        }

        .practice-cred-report .pcr-group-toggle {
            border: 0;
            background: transparent;
            padding: 0;
            margin-right: .45rem;
            color: var(--pcr-navy);
            line-height: 1;
            width: 1.25rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .practice-cred-report .pcr-group-name {
            font-weight: 700;
        }

        .practice-cred-report .pcr-group-meta {
            color: var(--pcr-muted);
            font-weight: 500;
            font-size: .8rem;
        }

        .practice-cred-report .pcr-view-all-row td {
            background: #fcfcfd;
            border-bottom: 1px solid #ececf3 !important;
            padding-top: .55rem;
            padding-bottom: .55rem;
        }

        .practice-cred-report .pcr-view-all {
            font-size: .8125rem;
            font-weight: 600;
            color: var(--pcr-navy);
            text-decoration: none;
        }

        .practice-cred-report .pcr-view-all:hover {
            color: var(--pcr-navy-deep);
            text-decoration: underline;
        }

        .practice-cred-report .pcr-empty {
            padding: 2.75rem 1rem;
            text-align: center;
            color: var(--pcr-muted);
        }

        .practice-cred-report .pcr-empty i {
            font-size: 1.75rem;
            opacity: .55;
            display: block;
            margin-bottom: .65rem;
        }

        .practice-cred-report .pcr-footer {
            display: flex;
            flex-direction: column;
            gap: .85rem;
        }

        .practice-cred-report .pcr-pager {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: .75rem 1rem;
            padding: .9rem 1.15rem;
        }

        .practice-cred-report .pcr-pager-summary {
            color: var(--pcr-muted);
            font-size: .875rem;
        }

        .practice-cred-report .pcr-pager-summary strong {
            color: var(--pcr-text);
            font-weight: 600;
        }

        .practice-cred-report .pcr-pagination {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .practice-cred-report .pcr-pagination .page-link {
            min-width: 2.25rem;
            height: 2.25rem;
            padding: 0 .7rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--pcr-border);
            border-radius: .5rem;
            background: #fff;
            color: #5d596c;
            font-size: .875rem;
            font-weight: 600;
            line-height: 1;
            transition: background .15s ease, border-color .15s ease, color .15s ease, box-shadow .15s ease;
        }

        .practice-cred-report .pcr-pagination .page-link:hover:not(:disabled) {
            background: var(--pcr-soft);
            border-color: #d0d2de;
            color: var(--pcr-navy);
        }

        .practice-cred-report .pcr-pagination .page-item.active .page-link {
            background: var(--pcr-navy);
            border-color: var(--pcr-navy);
            color: #fff;
            box-shadow: 0 2px 6px rgba(47, 58, 109, .22);
        }

        .practice-cred-report .pcr-pagination .page-link:disabled,
        .practice-cred-report .pcr-pagination .page-item.disabled .page-link {
            opacity: .42;
            cursor: not-allowed;
            pointer-events: none;
        }

        .practice-cred-report .pcr-pagination .page-link-nav {
            min-width: auto;
            padding: 0 .85rem;
            gap: .3rem;
        }

        .practice-cred-report .pcr-notes {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: .75rem 1.25rem;
            padding: 0 .15rem;
        }

        .practice-cred-report .pcr-legend {
            display: flex;
            flex-wrap: wrap;
            gap: .65rem 1.1rem;
            color: var(--pcr-muted);
            font-size: .8rem;
            font-weight: 500;
        }

        .practice-cred-report .pcr-legend-item {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
        }

        .practice-cred-report .pcr-footer-note {
            color: var(--pcr-muted);
            font-size: .78rem;
            line-height: 1.45;
        }

        .practice-cred-report .pcr-comment {
            max-width: 240px;
            color: #5d596c;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        @media (max-width: 991.98px) {
            .practice-cred-report .pcr-meta-item {
                justify-content: flex-start;
            }
        }
    </style>

    @php
        $pager = $report['pagination'];
        $currentPage = $pager['page'];
        $lastPage = $pager['last_page'];
        $totalPractices = $pager['total_practices'];
        $fromPractice = $totalPractices === 0 ? 0 : (($currentPage - 1) * $pager['per_page']) + 1;
        $toPractice = min($currentPage * $pager['per_page'], $totalPractices);
    @endphp

    <div class="pcr-shell">
        <div class="pcr-header">
            <a href="{{ route('admin.reports') }}" class="pcr-back">
                <i class="ti tabler-arrow-left"></i>
                Back to Reports
            </a>

            <div class="pcr-header-main">
                <div>
                    <h1 class="pcr-title">Total Credentialing Report by Practice</h1>
                    <p class="pcr-subtitle">Payer credentialing status and latest comments by practice and provider.</p>
                </div>
                <div class="pcr-meta">
                    <div class="pcr-meta-item">
                        <span>Report Date:</span>
                        <strong>{{ $reportDate }}</strong>
                    </div>
                    <div class="pcr-meta-item">
                        <span>Generated By:</span>
                        <strong>{{ $generatedBy }}</strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="pcr-panel pcr-filters">
            <div class="pcr-filters-label">Filters</div>
            <div class="row g-2 g-lg-3 align-items-end">
                <div class="col-6 col-md-4 col-xl">
                    <label class="form-label">Date From</label>
                    <input type="date" class="form-control" wire:model="draftDateFrom">
                </div>
                <div class="col-6 col-md-4 col-xl">
                    <label class="form-label">Date To</label>
                    <input type="date" class="form-control" wire:model="draftDateTo">
                </div>
                <div class="col-6 col-md-4 col-xl">
                    <label class="form-label">Practice</label>
                    <select class="form-select" wire:model.live="draftPracticeId">
                        <option value="">All Practices</option>
                        @foreach ($practices as $practice)
                            <option value="{{ $practice->id }}">{{ $practice->legal_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-4 col-xl">
                    <label class="form-label">Payer</label>
                    <select class="form-select" wire:model="draftPayerId">
                        <option value="">All Payers</option>
                        @foreach ($payers as $payer)
                            <option value="{{ $payer->id }}">{{ $payer->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-4 col-xl">
                    <label class="form-label">State</label>
                    <select class="form-select" wire:model="draftState">
                        <option value="">All States</option>
                        @foreach ($states as $state)
                            <option value="{{ $state }}">{{ $state }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-4 col-xl">
                    <label class="form-label">Provider</label>
                    <select class="form-select" wire:model="draftProviderId" @disabled(! $draftPracticeId)>
                        <option value="">{{ $draftPracticeId ? 'All Providers' : 'Select a practice first' }}</option>
                        @foreach ($providers as $provider)
                            <option value="{{ $provider->id }}">{{ $provider->user->name ?? 'Provider #'.$provider->id }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-xl-auto">
                    <div class="pcr-actions">
                        <button type="button" class="btn btn-pcr-primary" wire:click="runReport" wire:loading.attr="disabled" wire:target="runReport">
                            <span wire:loading.remove wire:target="runReport">Run Report</span>
                            <span wire:loading wire:target="runReport">Running...</span>
                        </button>
                        @if ($canExport)
                            <button type="button" class="btn btn-pcr-ghost" wire:click="openEmailModal">
                                <i class="ti tabler-mail me-1"></i>Email Report
                            </button>
                            <div class="btn-group">
                                <button type="button" class="btn btn-pcr-ghost" wire:click="downloadCsv">
                                    <i class="ti tabler-download me-1"></i>Download
                                </button>
                                <button type="button" class="btn btn-pcr-ghost dropdown-toggle dropdown-toggle-split"
                                    data-bs-toggle="dropdown" aria-expanded="false">
                                    <span class="visually-hidden">Download options</span>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <button type="button" class="dropdown-item" wire:click="downloadCsv">Download CSV</button>
                                    </li>
                                </ul>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="pcr-panel pcr-kpis">
            <div class="pcr-kpi-row">
                <div class="pcr-kpi">
                    <div class="pcr-kpi-label">Total Practices</div>
                    <div class="pcr-kpi-value">{{ number_format($report['totals']['practices']) }}</div>
                </div>
                <div class="pcr-kpi">
                    <div class="pcr-kpi-label">Total Providers</div>
                    <div class="pcr-kpi-value">{{ number_format($report['totals']['providers']) }}</div>
                </div>
                <div class="pcr-kpi is-divider">
                    <div class="pcr-kpi-label">Total Payer Enrollments</div>
                    <div class="pcr-kpi-value">{{ number_format($report['totals']['enrollments']) }}</div>
                </div>
                @foreach ($report['buckets'] as $bucket)
                    <div class="pcr-kpi">
                        <div class="pcr-kpi-label">
                            <span class="pcr-dot {{ $bucket['color'] }}"></span>{{ $bucket['label'] }}
                        </div>
                        <div class="pcr-kpi-value">
                            {{ number_format($bucket['count']) }}
                            <span class="pcr-kpi-pct">({{ number_format($bucket['percent'], 2) }}%)</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="pcr-panel pcr-table-wrap">
            <div class="table-responsive">
                <table class="table align-middle pcr-table">
                    <thead>
                        <tr>
                            <th>Practice / Provider</th>
                            <th>NPI</th>
                            <th>Payer</th>
                            <th>State</th>
                            <th>Status</th>
                            <th>Effective Date</th>
                            <th>Revalidation Due</th>
                            <th>Latest Comment</th>
                            <th>Last Updated</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($report['groups'] as $group)
                            @php
                                $collapsed = in_array($group['practice_id'], $collapsedPracticeIds, true);
                            @endphp
                            <tr class="pcr-group-row" wire:key="practice-group-{{ $group['practice_id'] }}">
                                <td colspan="8">
                                    <button type="button" class="pcr-group-toggle" wire:click="togglePractice({{ $group['practice_id'] }})" aria-expanded="{{ $collapsed ? 'false' : 'true' }}">
                                        <i class="ti tabler-chevron-{{ $collapsed ? 'right' : 'down' }}"></i>
                                    </button>
                                    <span class="pcr-group-name">{{ $group['index'] }}. {{ $group['practice_name'] }}</span>
                                </td>
                                <td class="text-end">
                                    <span class="pcr-group-meta">
                                        Providers: {{ $group['provider_count'] }} | Payer Enrollments: {{ $group['enrollment_count'] }}
                                    </span>
                                </td>
                            </tr>
                            @unless ($collapsed)
                                @foreach ($group['rows'] as $row)
                                    <tr class="pcr-data-row" wire:key="case-row-{{ $row['case_id'] }}">
                                        <td class="ps-5">{{ $row['provider_name'] ?: '—' }}</td>
                                        <td class="text-nowrap">{{ $row['npi'] ?: '—' }}</td>
                                        <td>{{ $row['payer_name'] ?: '—' }}</td>
                                        <td>{{ $row['state'] ?: '—' }}</td>
                                        <td>
                                            <span class="badge {{ $reportsService->statusBucketBadgeClass($row['status_bucket']) }}">
                                                {{ $row['status_name'] ?: '—' }}
                                            </span>
                                        </td>
                                        <td class="text-nowrap">{{ $row['effective_date'] ?: '—' }}</td>
                                        <td class="text-nowrap">{{ $row['revalidation_due'] ?: '—' }}</td>
                                        <td>
                                            <div class="pcr-comment" title="{{ $row['latest_comment'] }}">
                                                {{ $row['latest_comment'] ?: '—' }}
                                            </div>
                                        </td>
                                        <td class="small text-nowrap text-muted">{{ $row['last_updated'] ?: '—' }}</td>
                                    </tr>
                                @endforeach
                                @if ($group['enrollment_count'] > 0)
                                    <tr class="pcr-view-all-row">
                                        <td colspan="9" class="text-end">
                                            <a class="pcr-view-all"
                                                href="{{ route('admin.credentials', ['practice' => $group['practice_id']]) }}">
                                                View all {{ $group['enrollment_count'] }} enrollments for this practice
                                                <i class="ti tabler-arrow-right ms-1"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @endif
                            @endunless
                        @empty
                            <tr>
                                <td colspan="9">
                                    <div class="pcr-empty">
                                        <i class="ti tabler-report-search"></i>
                                        No enrollments match the current filters.<br>
                                        Adjust filters and click <strong>Run Report</strong>.
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="pcr-footer">
            <div class="pcr-panel pcr-pager">
                <div class="pcr-pager-summary">
                    @if ($totalPractices === 0)
                        No practices to display
                    @else
                        Showing <strong>{{ $fromPractice }}–{{ $toPractice }}</strong>
                        of <strong>{{ number_format($totalPractices) }}</strong> practices
                    @endif
                </div>

                <nav aria-label="Report pagination">
                    <ul class="pcr-pagination">
                        <li class="page-item {{ $currentPage <= 1 ? 'disabled' : '' }}">
                            <button type="button" class="page-link page-link-nav"
                                wire:click="gotoPage({{ max(1, $currentPage - 1) }})"
                                @disabled($currentPage <= 1)>
                                <i class="ti tabler-chevron-left"></i>
                                <span>Previous</span>
                            </button>
                        </li>

                        @for ($p = 1; $p <= $lastPage; $p++)
                            <li class="page-item {{ $p === $currentPage ? 'active' : '' }}">
                                <button type="button" class="page-link" wire:click="gotoPage({{ $p }})"
                                    @disabled($p === $currentPage)
                                    aria-current="{{ $p === $currentPage ? 'page' : 'false' }}">
                                    {{ $p }}
                                </button>
                            </li>
                        @endfor

                        <li class="page-item {{ $currentPage >= $lastPage ? 'disabled' : '' }}">
                            <button type="button" class="page-link page-link-nav"
                                wire:click="gotoPage({{ min($lastPage, $currentPage + 1) }})"
                                @disabled($currentPage >= $lastPage)>
                                <span>Next</span>
                                <i class="ti tabler-chevron-right"></i>
                            </button>
                        </li>
                    </ul>
                </nav>
            </div>

            <div class="pcr-notes">
                <div>
                    <div class="pcr-legend mb-2">
                        @foreach ($report['buckets'] as $bucket)
                            <span class="pcr-legend-item">
                                <span class="pcr-dot {{ $bucket['color'] }}"></span>{{ $bucket['label'] }}
                            </span>
                        @endforeach
                    </div>
                    <div class="pcr-footer-note">*All dates are in ET (Eastern Time)</div>
                </div>
                <div class="pcr-footer-note text-md-end">
                    Report includes enrollments across all states and payer types.
                </div>
            </div>
        </div>
    </div>

    @if ($showEmailModal)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,.45);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Email Report</h5>
                        <button type="button" class="btn-close" wire:click="closeEmailModal"></button>
                    </div>
                    <form wire:submit.prevent="emailReport">
                        <div class="modal-body">
                            <p class="text-muted small">Send the filtered Total Credentialing Report CSV using configured SMTP.</p>
                            <label class="form-label">Send to <span class="text-danger">*</span></label>
                            <input type="email" wire:model="emailTo"
                                class="form-control @error('emailTo') is-invalid @enderror"
                                placeholder="recipient@example.com">
                            @error('emailTo')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" wire:click="closeEmailModal">Cancel</button>
                            <button type="submit" class="btn btn-pcr-primary" wire:loading.attr="disabled" wire:target="emailReport">
                                <span wire:loading.remove wire:target="emailReport"><i class="ti tabler-send me-1"></i>Send Report</span>
                                <span wire:loading wire:target="emailReport">Sending...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
