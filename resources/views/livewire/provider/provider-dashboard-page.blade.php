<div>
    <div class="container-fluid flex-grow-1 px-3 px-md-4 py-3 py-md-4">
        <x-provider.page-header
            title="Welcome, {{ $provider->user->name }}"
            subtitle="Track your credentialing applications and upload requested documents."
        >
            <x-slot:actions>
                @if(can_do('portal.documents.view'))
                <a href="{{ route('provider.documents') }}" class="btn btn-primary">
                    <i class="ti tabler-upload me-1"></i> Upload Center
                </a>
                @endif
                @if(can_do('portal.cases.view'))
                <a href="{{ route('provider.cases') }}" class="btn btn-outline-secondary">
                    <i class="ti tabler-briefcase me-1"></i> My Applications
                </a>
                @endif
            </x-slot:actions>
        </x-provider.page-header>

        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-xl">
                <x-provider.stat-card label="Active Applications" :value="$stats['active_cases']" hint="Currently in progress" />
            </div>
            <div class="col-sm-6 col-xl">
                @if(can_do('portal.action_items.view'))
                <a href="{{ route('provider.action-items') }}" class="text-decoration-none">
                    <x-provider.stat-card label="Action Needed" :value="$stats['pending_action']" valueClass="text-warning" hint="Awaiting your documents" />
                </a>
                @else
                <x-provider.stat-card label="Action Needed" :value="$stats['pending_action']" valueClass="text-warning" hint="Awaiting your documents" />
                @endif
            </div>
            <div class="col-sm-6 col-xl">
                <x-provider.stat-card label="Approved" :value="$stats['approved']" valueClass="text-success" hint="Completed enrollments" />
            </div>
            <div class="col-sm-6 col-xl">
                <x-provider.stat-card label="Docs Expiring" :value="$stats['documents_expiring']" hint="Within 30 days" />
            </div>
            <div class="col-sm-6 col-xl">
                <x-provider.stat-card label="Checklist Pending" :value="$stats['checklist_pending']" hint="Required items outstanding" />
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-semibold">Outstanding Documents</h6>
                        @if(can_do('portal.documents.view'))
                        <a href="{{ route('provider.documents') }}" class="btn btn-sm btn-primary">Upload</a>
                        @endif
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Case</th>
                                    <th>Payer</th>
                                    <th>Document</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($outstanding as $item)
                                    <tr>
                                        <td class="fw-semibold">{{ $item['case_number'] }}</td>
                                        <td>{{ $item['payer'] }}</td>
                                        <td><span class="badge bg-label-warning">{{ $item['document_type'] }}</span></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-5">
                                            <i class="ti tabler-circle-check d-block mb-2 fs-3"></i>
                                            All required documents received.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white">
                        <h6 class="mb-0 fw-semibold">Recent Applications</h6>
                    </div>
                    <div class="list-group list-group-flush">
                        @forelse($recentCases as $case)
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="fw-semibold">{{ $case->case_number }}</div>
                                        <small class="text-muted">{{ $case->payer->name ?? '—' }}</small>
                                    </div>
                                    <span class="badge bg-label-primary">{{ $case->status->name ?? '—' }}</span>
                                </div>
                            </div>
                        @empty
                            <div class="list-group-item text-muted text-center py-4">No applications yet.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
