<div>
    <div class="container-fluid flex-grow-1 px-3 px-md-4 py-3 py-md-4">
        <x-practice.page-header
            title="Action Items"
            subtitle="Outstanding documents, pending applications, and follow-ups for your practice."
        >
            <x-slot:actions>
                @if(can_do('portal.documents.view'))
                <a href="{{ route('practice.documents') }}" class="btn btn-primary">
                    <i class="ti tabler-upload me-1"></i> Upload Center
                </a>
                @endif
            </x-slot:actions>
        </x-practice.page-header>

        <div class="row g-3 mb-4">
            <div class="col-sm-4">
                <x-practice.stat-card label="Total Action Items" :value="$items['total_count']" valueClass="text-warning" />
            </div>
            <div class="col-sm-4">
                <x-practice.stat-card label="Outstanding Documents" :value="$items['outstanding_documents']->count()" />
            </div>
            <div class="col-sm-4">
                <x-practice.stat-card label="Overdue Follow-ups" :value="$items['overdue_followups']->count()" valueClass="text-danger" />
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-semibold">Outstanding Documents</h6>
                        @if(can_do('portal.documents.view'))
                        <a href="{{ route('practice.documents') }}" class="btn btn-sm btn-primary">Upload</a>
                        @endif
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Provider</th>
                                    <th>Case</th>
                                    <th>Document</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($items['outstanding_documents'] as $item)
                                    <tr>
                                        <td>{{ $item['provider'] }}</td>
                                        <td class="fw-semibold">{{ $item['case_number'] }}</td>
                                        <td><span class="badge bg-label-warning">{{ $item['document_type'] }}</span></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-4">No outstanding documents.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white">
                        <h6 class="mb-0 fw-semibold">Applications Awaiting Action</h6>
                    </div>
                    <div class="list-group list-group-flush">
                        @forelse($items['pending_cases'] as $case)
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="fw-semibold">{{ $case->case_number }}</div>
                                        <small class="text-muted">
                                            {{ $case->provider->user->name ?? '—' }} · {{ $case->payer->name ?? '—' }}
                                        </small>
                                    </div>
                                    <span class="badge bg-label-warning">{{ $case->status->name ?? '—' }}</span>
                                </div>
                            </div>
                        @empty
                            <div class="list-group-item text-muted text-center py-4">No pending applications.</div>
                        @endforelse
                    </div>
                </div>

                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white">
                        <h6 class="mb-0 fw-semibold">Overdue Follow-ups</h6>
                    </div>
                    <div class="list-group list-group-flush">
                        @forelse($items['overdue_followups'] as $case)
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="fw-semibold">{{ $case->case_number }}</div>
                                        <small class="text-muted">
                                            {{ $case->provider->user->name ?? '—' }} · {{ $case->payer->name ?? '—' }}
                                        </small>
                                    </div>
                                    <small class="text-danger fw-semibold">
                                        Due {{ $case->next_follow_up_date?->format('m/d/Y') }}
                                    </small>
                                </div>
                            </div>
                        @empty
                            <div class="list-group-item text-muted text-center py-4">No overdue follow-ups.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
