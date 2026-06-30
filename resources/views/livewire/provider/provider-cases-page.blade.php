<div>
    <div class="container-fluid flex-grow-1 px-3 px-md-4 py-3 py-md-4">
        <x-provider.page-header
            title="My Credentialing Applications"
            subtitle="View status and document checklist progress for all your payer applications."
        />

        @php
            $activeCount = $cases->filter(fn ($c) => ! in_array($c->status?->dashboard_category ?? '', ['approved', 'closed']))->count();
            $actionCount = $cases->filter(fn ($c) => ($c->status?->dashboard_category ?? '') === 'provider')->count();
        @endphp

        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-md-4">
                <x-provider.stat-card label="Total Applications" :value="$cases->total()" />
            </div>
            <div class="col-sm-6 col-md-4">
                <x-provider.stat-card label="Active" :value="$activeCount" valueClass="text-primary" />
            </div>
            <div class="col-sm-6 col-md-4">
                <x-provider.stat-card label="Action Needed" :value="$actionCount" valueClass="text-warning" />
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Case #</th>
                            <th>Payer</th>
                            <th>Status</th>
                            <th>Checklist</th>
                            <th>Intake</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($cases as $case)
                            @php $c = $case->checklist_completion; @endphp
                            <tr wire:key="case-{{ $case->id }}">
                                <td class="fw-semibold">{{ $case->case_number }}</td>
                                <td>{{ $case->payer->name ?? '—' }}</td>
                                <td><span class="badge bg-label-primary">{{ $case->status->name ?? '—' }}</span></td>
                                <td style="min-width: 140px;">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress flex-grow-1" style="height: 6px;">
                                            <div class="progress-bar bg-primary" style="width: {{ $c['percent'] }}%"></div>
                                        </div>
                                        <small class="text-muted">{{ $c['received'] }}/{{ $c['total'] }}</small>
                                    </div>
                                </td>
                                <td>{{ $case->intake_date?->format('m/d/Y') ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-5">
                                    <i class="ti tabler-briefcase-off d-block mb-2 fs-3"></i>
                                    No credentialing applications found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($cases->hasPages())
            <div class="card-footer bg-white">{{ $cases->links() }}</div>
            @endif
        </div>
    </div>
</div>
