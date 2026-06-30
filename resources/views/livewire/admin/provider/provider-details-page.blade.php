<div class="container-fluid px-3 px-md-4 py-4">
    @php
        use App\Enums\ProviderStatus;
        $statusKey = is_object($provider->status) ? $provider->status->value : $provider->status;
        $statusBadge = match ($statusKey) {
            ProviderStatus::APPROVED->value => 'success',
            ProviderStatus::PENDING->value => 'warning',
            ProviderStatus::REJECTED->value => 'danger',
            default => 'secondary',
        };
    @endphp

    {{-- Header --}}
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-start gap-3">
            <div class="d-flex align-items-center gap-3">
                <span class="avatar rounded-circle bg-primary text-white fw-bold"
                    style="width: 48px; height: 48px; display: flex; align-items: center; justify-content: center;">
                    {{ substr($provider->user->name ?? 'P', 0, 1) }}
                </span>
                <div>
                    <h4 class="fw-bold text-primary mb-1">{{ $provider->user->name ?? 'N/A' }}</h4>
                    <p class="text-muted mb-0">{{ $provider->specialty->name ?? 'No Specialty' }} · NPI: {{ $provider->npi ?: 'N/A' }}</p>
                </div>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('admin.credentials.create') }}?provider={{ $provider->id }}" class="btn btn-outline-primary">
                    <i class="ti tabler-plus me-1"></i> New Application
                </a>
                <a href="{{ route('admin.providers.edit', $provider->id) }}" class="btn btn-primary">
                    <i class="ti tabler-edit me-1"></i> Edit
                </a>
                <a href="{{ route('admin.providers') }}" class="btn btn-outline-secondary">Back</a>
            </div>
        </div>
    </div>

    {{-- Tabs --}}
    <ul class="nav nav-tabs mb-4">
        <li class="nav-item">
            <button class="nav-link {{ $activeTab === 'overview' ? 'active' : '' }}" wire:click="setTab('overview')">Overview</button>
        </li>
        <li class="nav-item">
            <button class="nav-link {{ $activeTab === 'credentialing' ? 'active' : '' }}" wire:click="setTab('credentialing')">
                Credentialing
                @if($provider->credentialingCases->count())
                    <span class="badge bg-primary ms-1">{{ $provider->credentialingCases->count() }}</span>
                @endif
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link {{ $activeTab === 'timeline' ? 'active' : '' }}" wire:click="setTab('timeline')">Timeline</button>
        </li>
        <li class="nav-item">
            <button class="nav-link {{ $activeTab === 'documents' ? 'active' : '' }}" wire:click="setTab('documents')">Documents</button>
        </li>
    </ul>

    {{-- Overview Tab --}}
    @if ($activeTab === 'overview')
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-4">
                            <h5 class="mb-0">Provider Details</h5>
                            <span class="badge bg-label-{{ $statusBadge }} text-uppercase">{{ strtoupper($statusKey ?? 'N/A') }}</span>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted d-block">Portal Login</small>
                            <span class="fw-semibold">{{ $provider->user->email ?? 'N/A' }}</span>
                            <small class="text-muted d-block mt-1">Sign in at {{ url('/portal/login') }}</small>
                        </div>
                        <div class="mb-3"><small class="text-muted d-block">Phone</small><span class="fw-semibold">{{ $provider->user->phone ?? 'N/A' }}</span></div>
                        <div class="mb-3"><small class="text-muted d-block">CAQH ID</small><span class="fw-semibold">{{ $provider->caqh_id ?: 'N/A' }}</span></div>
                        <div class="mb-3"><small class="text-muted d-block">License</small><span class="fw-semibold">{{ $provider->license_number ? $provider->license_number . ($provider->license_state ? " ({$provider->license_state})" : '') : 'N/A' }}</span></div>
                        <div class="mb-3"><small class="text-muted d-block">DEA</small><span class="fw-semibold">{{ $provider->dea ?: 'N/A' }}</span></div>
                        <div class="mb-3"><small class="text-muted d-block">PECOS</small><span class="fw-semibold">{{ $provider->pecos_id ?: 'N/A' }}{{ $provider->pecos_enrolled ? ' (Enrolled)' : '' }}</span></div>
                        <div class="mb-3"><small class="text-muted d-block">Licensed States</small><span class="fw-semibold">{{ $provider->licensed_states ? implode(', ', $provider->licensed_states) : 'N/A' }}</span></div>
                        <div><small class="text-muted d-block">Address</small><span class="fw-semibold">{{ $provider->address ?: 'N/A' }}{{ $provider->city ? ', ' . $provider->city : '' }}{{ $provider->state ? ', ' . $provider->state : '' }} {{ $provider->zip }}</span></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-8">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body pb-0"><h5 class="mb-1">Assigned Practices</h5></div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light"><tr><th>Practice</th><th>Group NPI</th><th>Location</th><th>Primary</th></tr></thead>
                            <tbody>
                                @forelse($provider->practices as $practice)
                                    <tr>
                                        <td><a href="{{ route('admin.practices.show', $practice->id) }}" class="fw-semibold">{{ $practice->legal_name }}</a></td>
                                        <td><small>{{ $practice->group_npi ?: 'N/A' }}</small></td>
                                        <td><small>{{ $practice->primaryAddress?->city ?: 'N/A' }}</small></td>
                                        <td><span class="badge bg-label-{{ $practice->pivot->primary_flag ? 'success' : 'secondary' }}">{{ $practice->pivot->primary_flag ? 'Primary' : 'Secondary' }}</span></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center py-4 text-muted">No practices assigned</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Credentialing Tab --}}
    @if ($activeTab === 'credentialing')
        <div class="card shadow-sm border-0">
            <div class="card-body pb-0 d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Payer Applications</h5>
                <a href="{{ route('admin.credentials.create') }}" class="btn btn-sm btn-primary"><i class="ti tabler-plus me-1"></i>New</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Case #</th>
                            <th>Payer</th>
                            <th>Practice</th>
                            <th>Status</th>
                            <th>Delay Owner</th>
                            <th>Assigned To</th>
                            <th>Next Follow-up</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($provider->credentialingCases as $case)
                            <tr>
                                <td><a href="{{ route('admin.credentials') }}" wire:navigate class="fw-semibold">{{ $case->case_number }}</a></td>
                                <td>{{ $case->payer->name ?? 'N/A' }}</td>
                                <td><small>{{ $case->practice->legal_name ?? 'N/A' }}</small></td>
                                <td><span class="badge bg-label-info">{{ $case->status->name ?? 'N/A' }}</span></td>
                                <td><small>{{ $case->delayOwner->name ?? '—' }}</small></td>
                                <td><small>{{ $case->assignedAdmin->name ?? 'Unassigned' }}</small></td>
                                <td><small class="{{ $case->isOverdue() ? 'text-danger fw-bold' : '' }}">{{ $case->next_follow_up_date?->format('m/d/Y') ?: '—' }}</small></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center py-5 text-muted">No credentialing applications yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Timeline Tab --}}
    @if ($activeTab === 'timeline')
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0 fw-semibold">Activity Timeline</h6>
                <small class="text-muted">Recent activity across all provider credentialing cases</small>
            </div>
            <div class="card-body px-4 py-4">
                <x-admin.activity-timeline :activities="$timelineActivities" :show-case="true" />
            </div>
        </div>
    @endif

    {{-- Documents Tab --}}
    @if ($activeTab === 'documents')
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold">Provider Documents</h6>
                <a href="{{ route('admin.documents', ['provider' => $provider->id, 'upload' => 1]) }}" class="btn btn-sm btn-primary">
                    <i class="ti tabler-upload me-1"></i>Upload Document
                </a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Document</th>
                            <th>Type</th>
                            <th>Effective</th>
                            <th>Expiration</th>
                            <th>Status</th>
                            <th class="text-end">File</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($provider->documents as $document)
                            @php
                                $version = $document->versions->first();
                                $expired = $document->isExpired();
                                $expiring = $document->isExpiringSoon(30);
                            @endphp
                            <tr>
                                <td class="fw-semibold">{{ $document->title }}</td>
                                <td>{{ $document->documentType->name ?? '—' }}</td>
                                <td>{{ $document->effective_date?->format('m/d/Y') ?: '—' }}</td>
                                <td class="{{ $expired ? 'text-danger' : ($expiring ? 'text-warning' : '') }}">
                                    {{ $document->expiry_date?->format('m/d/Y') ?: '—' }}
                                </td>
                                <td>
                                    @if ($expired)
                                        <span class="badge bg-label-danger">Expired</span>
                                    @elseif ($expiring)
                                        <span class="badge bg-label-warning text-dark">Expiring Soon</span>
                                    @else
                                        <span class="badge bg-label-success">Active</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if ($version)
                                        <a href="{{ asset('storage/' . $version->file_path) }}" target="_blank"
                                            class="btn btn-sm btn-outline-secondary">
                                            <i class="ti tabler-download"></i>
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No documents on file for this provider.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
