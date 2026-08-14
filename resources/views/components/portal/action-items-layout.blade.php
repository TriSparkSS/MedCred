@props([
    'items',
    'groupedTasks',
    'casesShowRoute',
    'documentsRoute',
    'showProvider' => false,
    'headerComponent' => 'provider',
])

<div>
    <div class="container-fluid flex-grow-1 px-3 px-md-4 py-3 py-md-4">
        @if ($headerComponent === 'provider')
            <x-provider.page-header
                title="Action Items"
                subtitle="Outstanding documents, pending applications, and follow-ups requiring your attention."
            >
                <x-slot:actions>
                    @if(can_do('portal.documents.view'))
                    <a href="{{ $documentsRoute }}" class="btn btn-primary">
                        <i class="ti tabler-upload me-1"></i> Upload Center
                    </a>
                    @endif
                </x-slot:actions>
            </x-provider.page-header>
        @else
            <x-practice.page-header
                title="Action Items"
                subtitle="Outstanding documents, pending applications, and follow-ups for your practice."
            >
                <x-slot:actions>
                    @if(can_do('portal.documents.view'))
                    <a href="{{ $documentsRoute }}" class="btn btn-primary">
                        <i class="ti tabler-upload me-1"></i> Upload Center
                    </a>
                    @endif
                </x-slot:actions>
            </x-practice.page-header>
        @endif

        <div class="row g-3 mb-4">
            <div class="col-md-3 col-sm-6">
                @if ($headerComponent === 'provider')
                    <x-provider.stat-card label="Total Action Items" :value="$items['total_count']" valueClass="text-warning" />
                @else
                    <x-practice.stat-card label="Total Action Items" :value="$items['total_count']" valueClass="text-warning" />
                @endif
            </div>
            <div class="col-md-3 col-sm-6">
                @if ($headerComponent === 'provider')
                    <x-provider.stat-card label="Outstanding Documents" :value="$items['outstanding_documents']->count()" />
                @else
                    <x-practice.stat-card label="Outstanding Documents" :value="$items['outstanding_documents']->count()" />
                @endif
            </div>
            <div class="col-md-3 col-sm-6">
                @if ($headerComponent === 'provider')
                    <x-provider.stat-card label="Overdue Follow-ups" :value="$items['overdue_followups']->count()" valueClass="text-danger" />
                @else
                    <x-practice.stat-card label="Overdue Follow-ups" :value="$items['overdue_followups']->count()" valueClass="text-danger" />
                @endif
            </div>
            <div class="col-md-3 col-sm-6">
                @if ($headerComponent === 'provider')
                    <x-provider.stat-card label="Open Tasks" :value="$items['provider_tasks']->count()" />
                @else
                    <x-practice.stat-card label="Open Tasks" :value="$items['provider_tasks']->count()" />
                @endif
            </div>
        </div>

        <div class="mb-4">
            <h5 class="fw-bold mb-3"><i class="ti tabler-layout-kanban me-2 text-primary"></i>Tasks &amp; Follow-ups</h5>
            @if ($items['provider_tasks']->isNotEmpty())
                <x-portal.task-kanban
                    :grouped-tasks="$groupedTasks"
                    :case-show-route="$casesShowRoute"
                    :documents-route="$documentsRoute"
                    :show-provider="$showProvider"
                />
            @else
                <div class="card shadow-sm border-0">
                    <div class="card-body text-center text-muted py-5">
                        <i class="ti tabler-checkbox d-block mb-2 fs-3"></i>
                        No open tasks or follow-ups.
                    </div>
                </div>
            @endif
        </div>

        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-semibold">Outstanding Documents</h6>
                        @if(can_do('portal.documents.view'))
                        <a href="{{ $documentsRoute }}" class="btn btn-sm btn-primary">Upload</a>
                        @endif
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    @if ($showProvider)
                                        <th>Provider</th>
                                    @endif
                                    <th>Case</th>
                                    <th>Payer</th>
                                    <th>Document</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($items['outstanding_documents'] as $item)
                                    <tr>
                                        @if ($showProvider)
                                            <td>{{ $item['provider'] ?? '—' }}</td>
                                        @endif
                                        <td class="fw-semibold">
                                            <a href="{{ route($casesShowRoute, $item['case_id']) }}" class="text-decoration-none">
                                                {{ $item['case_number'] }}
                                            </a>
                                        </td>
                                        <td>{{ $item['payer'] }}</td>
                                        <td><span class="badge bg-label-warning">{{ $item['document_type'] }}</span></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ $showProvider ? 4 : 3 }}" class="text-center text-muted py-4">
                                            <i class="ti tabler-file-off d-block mb-2"></i>
                                            No outstanding documents.
                                        </td>
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
                        <h6 class="mb-0 fw-semibold">{{ $showProvider ? 'Applications Awaiting Action' : 'Applications Awaiting Your Action' }}</h6>
                    </div>
                    <div class="list-group list-group-flush">
                        @forelse($items['pending_cases'] as $case)
                            <a href="{{ route($casesShowRoute, $case) }}" class="list-group-item list-group-item-action">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="fw-semibold">{{ $case->case_number }}</div>
                                        <small class="text-muted">
                                            @if ($showProvider)
                                                {{ $case->provider->user->name ?? '—' }} ·
                                            @endif
                                            {{ $case->payer->name ?? '—' }}
                                        </small>
                                    </div>
                                    <span class="badge bg-label-warning">{{ $case->status->name ?? '—' }}</span>
                                </div>
                            </a>
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
                            <a href="{{ route($casesShowRoute, $case) }}" class="list-group-item list-group-item-action">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="fw-semibold">{{ $case->case_number }}</div>
                                        <small class="text-muted">
                                            @if ($showProvider)
                                                {{ $case->provider->user->name ?? '—' }} ·
                                            @endif
                                            {{ $case->payer->name ?? '—' }}
                                        </small>
                                    </div>
                                    <small class="text-danger fw-semibold">
                                        Due {{ $case->next_follow_up_date?->format('m/d/Y') }}
                                    </small>
                                </div>
                            </a>
                        @empty
                            <div class="list-group-item text-muted text-center py-4">No overdue follow-ups.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
