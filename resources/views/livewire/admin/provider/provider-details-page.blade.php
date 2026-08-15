<div class="container-fluid px-3 px-md-4 py-4">
    @php
        use App\Enums\DocumentVerificationStatus;
        use App\Enums\ProviderStatus;

        $statusKey = $provider->status instanceof ProviderStatus
            ? $provider->status->value
            : ($provider->status ?? null);
        $statusBadge = match ($statusKey) {
            ProviderStatus::APPROVED->value => 'success',
            ProviderStatus::PENDING->value => 'warning',
            ProviderStatus::REJECTED->value => 'danger',
            default => 'secondary',
        };
        $statusLabel = $provider->status instanceof ProviderStatus
            ? strtoupper($provider->status->value)
            : strtoupper($statusKey ?? 'N/A');

        $closedCategories = ['approved', 'closed'];
        $openCases = $cases->filter(
            fn ($case) => ! in_array($case->status?->dashboard_category, $closedCategories, true)
        );
        $openCasesCount = $openCases->count();
        $latestCase = $cases->sortByDesc(fn ($case) => $case->last_action_at ?? $case->created_at)->first();
        $latestCaseFollowUp = $latestCase?->next_follow_up_date;
        $expiredDocs = $provider->documents->filter(fn ($doc) => $doc->isExpired());
        $expiringDocs = $provider->documents->filter(fn ($doc) => ! $doc->isExpired() && $doc->isExpiringSoon(30));

        $verificationBadge = function ($status): string {
            $key = $status instanceof DocumentVerificationStatus ? $status->value : ($status ?? '');

            return match ($key) {
                DocumentVerificationStatus::Verified->value => 'success',
                DocumentVerificationStatus::Uploaded->value => 'info',
                DocumentVerificationStatus::Rejected->value => 'danger',
                DocumentVerificationStatus::Expired->value => 'danger',
                DocumentVerificationStatus::Superseded->value => 'secondary',
                DocumentVerificationStatus::Missing->value => 'warning',
                default => 'secondary',
            };
        };

        $verificationLabel = function ($status): string {
            if ($status instanceof DocumentVerificationStatus) {
                return $status->label();
            }

            return DocumentVerificationStatus::tryFrom((string) $status)?->label() ?? ucfirst((string) $status ?: 'Unknown');
        };

        $billingFieldLabels = [
            'approval_date' => 'Approval Date',
            'effective_date' => 'Effective Date',
            'payer_provider_id' => 'Payer Provider ID',
            'approval_letter' => 'Verified Approval Letter',
        ];
    @endphp

    {{-- Header --}}
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-start gap-3">
            <div class="d-flex align-items-center gap-3">
                <span class="avatar rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center"
                    style="width: 48px; height: 48px;">
                    {{ substr($provider->user->name ?? 'P', 0, 1) }}
                </span>
                <div>
                    <h4 class="fw-bold text-primary mb-1">{{ $provider->user->name ?? 'N/A' }}</h4>
                    <p class="text-muted mb-0">
                        {{ $provider->specialty->name ?? 'No Specialty' }}
                        · NPI: {{ $provider->npi ?: 'N/A' }}
                    </p>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                @if (auth('admin')->user()?->can('admin.credentials.create'))
                <a href="{{ route('admin.credentials.create') }}?provider={{ $provider->id }}" class="btn btn-outline-primary">
                    <i class="ti tabler-plus me-1"></i> New Application
                </a>
                @endif
                @if (auth('admin')->user()?->can('admin.providers.edit'))
                <a href="{{ route('admin.providers.edit', $provider->id) }}" class="btn btn-primary">
                    <i class="ti tabler-edit me-1"></i> Edit
                </a>
                @endif
                <a href="{{ route('admin.providers') }}" class="btn btn-outline-secondary">Back</a>
            </div>
        </div>
    </div>

    {{-- Tabs --}}
    <ul class="nav nav-tabs mb-4 flex-nowrap overflow-auto">
        <li class="nav-item">
            <button type="button" class="nav-link {{ $activeTab === 'overview' ? 'active' : '' }}" wire:click="setTab('overview')">Overview</button>
        </li>
        <li class="nav-item">
            <button type="button" class="nav-link {{ $activeTab === 'practice_ids' ? 'active' : '' }}" wire:click="setTab('practice_ids')">Practice IDs</button>
        </li>
        <li class="nav-item">
            <button type="button" class="nav-link {{ $activeTab === 'licenses' ? 'active' : '' }}" wire:click="setTab('licenses')">Licenses</button>
        </li>
        <li class="nav-item">
            <button type="button" class="nav-link {{ $activeTab === 'documents' ? 'active' : '' }}" wire:click="setTab('documents')">
                Documents
                @if ($expiredDocs->count())
                    <span class="badge bg-danger ms-1">{{ $expiredDocs->count() }}</span>
                @endif
            </button>
        </li>
        <li class="nav-item">
            <button type="button" class="nav-link {{ $activeTab === 'payer_applications' ? 'active' : '' }}" wire:click="setTab('payer_applications')">
                Payer Applications
                @if ($openCasesCount)
                    <span class="badge bg-primary ms-1">{{ $openCasesCount }}</span>
                @endif
            </button>
        </li>
        <li class="nav-item">
            <button type="button" class="nav-link {{ $activeTab === 'communication' ? 'active' : '' }}" wire:click="setTab('communication')">
                Communication
                @if ($emails->count())
                    <span class="badge bg-label-primary ms-1">{{ $emails->count() }}</span>
                @endif
            </button>
        </li>
        <li class="nav-item">
            <button type="button" class="nav-link {{ $activeTab === 'tasks' ? 'active' : '' }}" wire:click="setTab('tasks')">
                Tasks & Follow-Ups
                @if ($openTaskCount)
                    <span class="badge bg-warning ms-1">{{ $openTaskCount }}</span>
                @endif
            </button>
        </li>
        <li class="nav-item">
            <button type="button" class="nav-link {{ $activeTab === 'delay' ? 'active' : '' }}" wire:click="setTab('delay')">Delay</button>
        </li>
        <li class="nav-item">
            <button type="button" class="nav-link {{ $activeTab === 'timeline' ? 'active' : '' }}" wire:click="setTab('timeline')">Timeline</button>
        </li>
        <li class="nav-item">
            <button type="button" class="nav-link {{ $activeTab === 'billing' ? 'active' : '' }}" wire:click="setTab('billing')">
                Billing
                @if ($billingCases->count())
                    <span class="badge bg-label-success ms-1">{{ $billingCases->count() }}</span>
                @endif
            </button>
        </li>
    </ul>

    {{-- 1. Overview --}}
    @if ($activeTab === 'overview')
        <div class="row g-4 mb-4">
            <div class="col-sm-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <p class="text-uppercase text-muted small mb-1">Open Applications</p>
                        <h3 class="fw-bold mb-0 text-primary">{{ $openCasesCount }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-sm-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <p class="text-uppercase text-muted small mb-1">Next Follow-up (Latest Case)</p>
                        <h3 class="fw-bold mb-0 {{ $latestCaseFollowUp && $latestCase?->isOverdue() ? 'text-danger' : '' }}">
                            {{ $latestCaseFollowUp?->format('m/d/Y') ?: '—' }}
                        </h3>
                        @if ($latestCase)
                            <small class="text-muted">{{ $latestCase->case_number }}</small>
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-sm-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <p class="text-uppercase text-muted small mb-1">Document Alerts</p>
                        <h3 class="fw-bold mb-0 {{ $expiredDocs->count() ? 'text-danger' : ($expiringDocs->count() ? 'text-warning' : '') }}">
                            {{ $expiredDocs->count() }} expired
                        </h3>
                        @if ($expiringDocs->count())
                            <small class="text-muted">{{ $expiringDocs->count() }} expiring within 30 days</small>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        @if ($expiredDocs->count())
            <div class="alert alert-danger d-flex align-items-start gap-2 mb-4" role="alert">
                <i class="ti tabler-alert-triangle mt-1"></i>
                <div>
                    <strong>Expired documents on file:</strong>
                    {{ $expiredDocs->pluck('title')->join(', ') }}
                </div>
            </div>
        @elseif ($expiringDocs->count())
            <div class="alert alert-warning d-flex align-items-start gap-2 mb-4" role="alert">
                <i class="ti tabler-clock-exclamation mt-1"></i>
                <div>
                    <strong>Documents expiring soon:</strong>
                    {{ $expiringDocs->pluck('title')->join(', ') }}
                </div>
            </div>
        @endif

        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Demographics</h5>
                        <span class="badge bg-label-{{ $statusBadge }} text-uppercase">{{ $statusLabel }}</span>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <small class="text-muted d-block">Full Name</small>
                                <span class="fw-semibold">{{ $provider->user->name ?? 'N/A' }}</span>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted d-block">Specialty</small>
                                <span class="fw-semibold">{{ $provider->specialty->name ?? 'N/A' }}</span>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted d-block">NPI</small>
                                <span class="fw-semibold">{{ $provider->npi ?: 'N/A' }}</span>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted d-block">Taxonomy Code</small>
                                <span class="fw-semibold">{{ $provider->taxonomy_code ?: 'N/A' }}</span>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted d-block">{{ auth('admin')->user()?->can('admin.portal-credentials.manage') ? 'Portal Login' : 'Email' }}</small>
                                <span class="fw-semibold">{{ $provider->user->email ?? 'N/A' }}</span>
                                @if (auth('admin')->user()?->can('admin.portal-credentials.manage'))
                                <small class="text-muted d-block mt-1">Sign in at {{ url('/portal/login') }}</small>
                                @endif
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted d-block">Phone</small>
                                <span class="fw-semibold">{{ $provider->user->phone ?? 'N/A' }}</span>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted d-block">CAQH ID</small>
                                <span class="fw-semibold">{{ $provider->caqh_id ?: 'N/A' }}</span>
                            </div>
                            <div class="col-12">
                                <small class="text-muted d-block">Address</small>
                                <span class="fw-semibold">
                                    {{ $provider->address ?: 'N/A' }}{{ $provider->city ? ', ' . $provider->city : '' }}{{ $provider->state ? ', ' . $provider->state : '' }}
                                    {{ $provider->zip }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white border-bottom">
                        <h5 class="mb-0">Quick Summary</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled mb-0">
                            <li class="d-flex justify-content-between py-2 border-bottom">
                                <span class="text-muted">Provider Status</span>
                                <span class="badge bg-label-{{ $statusBadge }}">{{ $statusLabel }}</span>
                            </li>
                            <li class="d-flex justify-content-between py-2 border-bottom">
                                <span class="text-muted">Total Applications</span>
                                <span class="fw-semibold">{{ $cases->count() }}</span>
                            </li>
                            <li class="d-flex justify-content-between py-2 border-bottom">
                                <span class="text-muted">Open Tasks</span>
                                <a href="#" wire:click.prevent="setTab('tasks')" class="fw-semibold text-decoration-none">{{ $openTaskCount }}</a>
                            </li>
                            <li class="d-flex justify-content-between py-2 border-bottom">
                                <span class="text-muted">Practice Assignments</span>
                                <span class="fw-semibold">{{ $provider->practices->count() }}</span>
                            </li>
                            <li class="d-flex justify-content-between py-2">
                                <span class="text-muted">Documents on File</span>
                                <span class="fw-semibold">{{ $provider->documents->count() }}</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- 2. Practice IDs --}}
    @if ($activeTab === 'practice_ids')
        <div class="row g-4">
            <div class="col-12">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white border-bottom">
                        <h5 class="mb-0">Provider Practice Locations</h5>
                        <small class="text-muted">Linked practice and location identifiers for credentialing</small>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Practice</th>
                                    <th>Location</th>
                                    <th>Role</th>
                                    <th>Primary</th>
                                    <th>Start</th>
                                    <th>End</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($provider->providerPracticeLocations as $ppl)
                                    <tr>
                                        <td>
                                            @if ($ppl->practice)
                                                <a href="{{ route('admin.practices.show', $ppl->practice_id) }}" class="fw-semibold">{{ $ppl->practice->legal_name }}</a>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($ppl->location)
                                                <div class="fw-semibold">{{ $ppl->location->name ?: 'Location #' . $ppl->location_id }}</div>
                                                <small class="text-muted">
                                                    {{ $ppl->location->city }}{{ $ppl->location->state ? ', ' . $ppl->location->state : '' }}
                                                    {{ $ppl->location->zip_code }}
                                                </small>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td>{{ $ppl->role ?: '—' }}</td>
                                        <td>
                                            <span class="badge bg-label-{{ $ppl->is_primary ? 'success' : 'secondary' }}">
                                                {{ $ppl->is_primary ? 'Primary' : 'Secondary' }}
                                            </span>
                                        </td>
                                        <td><small>{{ $ppl->start_date?->format('m/d/Y') ?: '—' }}</small></td>
                                        <td><small>{{ $ppl->end_date?->format('m/d/Y') ?: '—' }}</small></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-5 text-muted">No practice location links configured.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-12">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white border-bottom">
                        <h5 class="mb-0">Practice Assignments (Pivot)</h5>
                        <small class="text-muted">Provider-to-practice relationships</small>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Practice</th>
                                    <th>Group NPI</th>
                                    <th>Location</th>
                                    <th>Primary</th>
                                    <th>Start</th>
                                    <th>End</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($provider->practices as $practice)
                                    <tr>
                                        <td>
                                            <a href="{{ route('admin.practices.show', $practice->id) }}" class="fw-semibold">{{ $practice->legal_name }}</a>
                                        </td>
                                        <td><small>{{ $practice->group_npi ?: 'N/A' }}</small></td>
                                        <td><small>{{ $practice->primaryAddress?->city ?: 'N/A' }}</small></td>
                                        <td>
                                            <span class="badge bg-label-{{ $practice->pivot->primary_flag ? 'success' : 'secondary' }}">
                                                {{ $practice->pivot->primary_flag ? 'Primary' : 'Secondary' }}
                                            </span>
                                        </td>
                                        <td><small>{{ $practice->pivot->start_date ? \Illuminate\Support\Carbon::parse($practice->pivot->start_date)->format('m/d/Y') : '—' }}</small></td>
                                        <td><small>{{ $practice->pivot->end_date ? \Illuminate\Support\Carbon::parse($practice->pivot->end_date)->format('m/d/Y') : '—' }}</small></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-5 text-muted">No practices assigned.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- 3. Licenses --}}
    @if ($activeTab === 'licenses')
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white border-bottom">
                        <h5 class="mb-0">Professional Licenses</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <small class="text-muted d-block">State License</small>
                            <span class="fw-semibold">
                                {{ $provider->license_number ? $provider->license_number . ($provider->license_state ? " ({$provider->license_state})" : '') : 'N/A' }}
                            </span>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted d-block">DEA</small>
                            <span class="fw-semibold">{{ $provider->dea ?: 'N/A' }}</span>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted d-block">CDS</small>
                            <span class="fw-semibold">
                                {{ $provider->cds_number ? $provider->cds_number . ($provider->cds_state ? " ({$provider->cds_state})" : '') : 'N/A' }}
                            </span>
                        </div>
                        <div>
                            <small class="text-muted d-block">Licensed States</small>
                            <span class="fw-semibold">{{ $provider->licensed_states ? implode(', ', $provider->licensed_states) : 'N/A' }}</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white border-bottom">
                        <h5 class="mb-0">Certifications & Coverage</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <small class="text-muted d-block">Board Certification</small>
                            <span class="fw-semibold">{{ $provider->board_certification ?: 'N/A' }}</span>
                            @if ($provider->board_cert_expiry)
                                <small class="text-muted d-block mt-1">Expires {{ $provider->board_cert_expiry->format('m/d/Y') }}</small>
                            @endif
                        </div>
                        <div class="mb-3">
                            <small class="text-muted d-block">Malpractice Insurance</small>
                            <span class="fw-semibold">{{ $provider->malpractice_carrier ?: 'N/A' }}</span>
                            @if ($provider->malpractice_policy_number)
                                <small class="text-muted d-block mt-1">Policy #{{ $provider->malpractice_policy_number }}</small>
                            @endif
                            @if ($provider->malpractice_expiry)
                                <small class="text-muted d-block">Expires {{ $provider->malpractice_expiry->format('m/d/Y') }}</small>
                            @endif
                        </div>
                        <div>
                            <small class="text-muted d-block">PECOS</small>
                            <span class="fw-semibold">
                                {{ $provider->pecos_id ?: 'N/A' }}{{ $provider->pecos_enrolled ? ' (Enrolled)' : '' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- 4. Documents --}}
    @if ($activeTab === 'documents')
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-0 fw-semibold">Provider Documents</h6>
                    <small class="text-muted">Verification status and expiration tracking</small>
                </div>
                @if (auth('admin')->user()?->can('admin.documents.upload'))
                    <a href="{{ route('admin.documents', ['provider' => $provider->id, 'upload' => 1]) }}" class="btn btn-sm btn-primary">
                        <i class="ti tabler-upload me-1"></i>Upload Document
                    </a>
                @endif
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Document</th>
                            <th>Type</th>
                            <th>Effective</th>
                            <th>Expiration</th>
                            <th>Verification</th>
                            <th>Expiry Status</th>
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
                                    <span class="badge bg-label-{{ $verificationBadge($document->verification_status) }}">
                                        {{ $verificationLabel($document->verification_status) }}
                                    </span>
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
                                <td colspan="7" class="text-center text-muted py-4">No documents on file for this provider.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- 5. Payer Applications --}}
    @if ($activeTab === 'payer_applications')
        <div class="card shadow-sm border-0">
            <div class="card-body pb-0 d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Payer Applications</h5>
                @if (auth('admin')->user()?->can('admin.credentials.create'))
                    <a href="{{ route('admin.credentials.create') }}?provider={{ $provider->id }}" class="btn btn-sm btn-primary">
                        <i class="ti tabler-plus me-1"></i>New
                    </a>
                @endif
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
                        @forelse($cases as $case)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.credentials') }}" wire:navigate class="fw-semibold">{{ $case->case_number }}</a>
                                </td>
                                <td>{{ $case->payer->name ?? 'N/A' }}</td>
                                <td><small>{{ $case->practice->legal_name ?? 'N/A' }}</small></td>
                                <td><span class="badge bg-label-info">{{ $case->status->name ?? 'N/A' }}</span></td>
                                <td><small>{{ $case->delayOwner->name ?? '—' }}</small></td>
                                <td><small>{{ $case->assignedAdmin->name ?? 'Unassigned' }}</small></td>
                                <td>
                                    <small class="{{ $case->isOverdue() ? 'text-danger fw-bold' : '' }}">
                                        {{ $case->next_follow_up_date?->format('m/d/Y') ?: '—' }}
                                    </small>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">No credentialing applications yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- 6. Communication --}}
    @if ($activeTab === 'communication')
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-0 fw-semibold">Email Correspondence</h6>
                    <small class="text-muted">Inbound and outbound messages linked to this provider</small>
                </div>
                <a href="{{ route('admin.email.dashboard') }}" class="btn btn-sm btn-outline-primary">
                    <i class="ti tabler-mail me-1"></i>Email Center
                </a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Direction</th>
                            <th>Subject</th>
                            <th>Case</th>
                            <th>From / To</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($emails as $email)
                            <tr>
                                <td>
                                    <span class="badge bg-label-secondary">Linked</span>
                                </td>
                                <td class="fw-semibold text-break"><code class="small">{{ $email->message_id }}</code></td>
                                <td>
                                    @if ($email->credentialingCase)
                                        <small>{{ $email->credentialingCase->case_number }}</small>
                                    @else
                                        <span class="badge bg-label-warning">Unlinked</span>
                                    @endif
                                </td>
                                <td colspan="2">
                                    <a href="{{ route('admin.email.dashboard') }}" class="small">Open Email Center to view live message</a>
                                </td>
                                <td>
                                    <small>{{ $email->created_at?->format('m/d/Y g:i A') ?: '—' }}</small>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">No email correspondence on record.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- 7. Tasks & Follow-Ups --}}
    @if ($activeTab === 'tasks')
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h6 class="mb-0 fw-semibold">Tasks & Follow-Ups</h6>
                    <small class="text-muted">{{ $openTaskCount }} open task{{ $openTaskCount !== 1 ? 's' : '' }} for this provider</small>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    @if (auth('admin')->user()?->can('admin.tasks.manage'))
                        <button wire:click="openCreateTaskModal" class="btn btn-sm btn-primary">
                            <i class="ti tabler-plus me-1"></i>Create Task
                        </button>
                    @endif
                    @if (auth('admin')->user()?->can('admin.tasks.view'))
                        <a href="{{ route('admin.tasks.kanban', ['provider_id' => $provider->id]) }}" class="btn btn-sm btn-outline-primary">
                            <i class="ti tabler-layout-kanban me-1"></i>View Board
                        </a>
                    @endif
                </div>
            </div>
            <div class="card-body border-bottom pb-0">
                <div class="d-flex flex-wrap gap-2 mb-3">
                    @foreach (['open' => 'Open', 'completed' => 'Completed', 'upcoming' => 'Upcoming', 'overdue' => 'Overdue', 'escalated' => 'Escalated'] as $key => $label)
                        <button wire:click="setTaskSection('{{ $key }}')"
                            class="btn btn-sm {{ $taskSection === $key ? 'btn-primary' : 'btn-outline-secondary' }}">
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Task Name</th>
                            <th>Assigned To</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Due Date</th>
                            <th>Follow-up Date</th>
                            <th>Linked Case</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($providerTasks as $task)
                            @php
                                $column = $task->kanbanColumn();
                                $dueClass = match ($column) {
                                    'overdue' => 'text-danger fw-bold',
                                    'due_today' => 'text-warning fw-semibold',
                                    default => '',
                                };
                            @endphp
                            <tr wire:key="provider-task-{{ $task->id }}">
                                <td>
                                    <div class="fw-semibold">{{ $task->title }}</div>
                                    <small class="text-muted">{{ $taskTypeLabel($task->task_type ?? 'manual') }}</small>
                                </td>
                                <td><small>{{ $task->assignedAdmin->name ?? 'Unassigned' }}</small></td>
                                <td><small>{{ $task->priority->name ?? '—' }}</small></td>
                                <td><span class="badge bg-label-secondary">{{ $task->status?->label() ?? 'Open' }}</span></td>
                                <td><small class="{{ $dueClass }}">{{ $task->due_date?->format('m/d/Y') ?: '—' }}</small></td>
                                <td><small>{{ $task->follow_up_date?->format('m/d/Y') ?: '—' }}</small></td>
                                <td>
                                    @if ($task->credentialingCase)
                                        <a href="{{ route('admin.credentials', ['search' => $task->credentialingCase->case_number]) }}"
                                            class="text-decoration-none">
                                            {{ $task->credentialingCase->case_number }}
                                        </a>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <button wire:click="openTaskDetail({{ $task->id }})" class="btn btn-sm btn-outline-primary">Details</button>
                                    @if (!$task->isCompleted() && auth('admin')->user()?->can('admin.tasks.manage'))
                                        <button wire:click="completeProviderTask({{ $task->id }})" class="btn btn-sm btn-outline-success">Complete</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">No tasks in this section.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($showTaskModal)
            <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,.5);">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Create Task</h5>
                            <button type="button" class="btn-close" wire:click="$set('showTaskModal', false)"></button>
                        </div>
                        <form wire:submit.prevent="saveTask">
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">Title <span class="text-danger">*</span></label>
                                    <input type="text" wire:model="taskForm.title" class="form-control">
                                    @error('taskForm.title')<div class="text-danger small">{{ $message }}</div>@enderror
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Description</label>
                                    <textarea wire:model="taskForm.description" rows="2" class="form-control"></textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Assign To (Revantage Staff)</label>
                                    <select wire:model="taskForm.assigned_admin_id" class="form-select">
                                        <option value="">Unassigned</option>
                                        @foreach ($admins as $admin)
                                            <option value="{{ $admin->id }}">{{ $admin->displayLabel() }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Due Date</label>
                                    <input type="date" wire:model="taskForm.due_date" class="form-control">
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" wire:click="$set('showTaskModal', false)">Cancel</button>
                                <button type="submit" class="btn btn-primary">Create</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif

        <livewire:admin.task.task-detail-drawer />
    @endif

    {{-- 8. Delay --}}
    @if ($activeTab === 'delay')
        <div class="row g-4">
            <div class="col-12">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white border-bottom">
                        <h5 class="mb-0">Current Delay Owners by Case</h5>
                        <small class="text-muted">Active delay ownership across payer applications</small>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Case #</th>
                                    <th>Payer</th>
                                    <th>Status</th>
                                    <th>Current Delay Owner</th>
                                    <th>Next Follow-up</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($cases as $case)
                                    <tr>
                                        <td class="fw-semibold">{{ $case->case_number }}</td>
                                        <td>{{ $case->payer->name ?? 'N/A' }}</td>
                                        <td><span class="badge bg-label-info">{{ $case->status->name ?? 'N/A' }}</span></td>
                                        <td>
                                            @if ($case->delayOwner)
                                                <span class="badge bg-label-warning">{{ $case->delayOwner->name }}</span>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            <small class="{{ $case->isOverdue() ? 'text-danger fw-bold' : '' }}">
                                                {{ $case->next_follow_up_date?->format('m/d/Y') ?: '—' }}
                                            </small>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-5 text-muted">No cases to display.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-12">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white border-bottom">
                        <h5 class="mb-0">Delay Owner History</h5>
                        <small class="text-muted">Recent delay ownership changes</small>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Case</th>
                                    <th>Previous Owner</th>
                                    <th>New Owner</th>
                                    <th>Source</th>
                                    <th>Changed By</th>
                                    <th>Reason</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($delayHistories as $history)
                                    <tr>
                                        <td><small>{{ $history->created_at?->format('m/d/Y g:i A') }}</small></td>
                                        <td>
                                            <small>{{ $history->credentialingCase->case_number ?? 'Case #' . $history->credentialing_case_id }}</small>
                                        </td>
                                        <td><small>{{ $history->previousDelayOwner->name ?? '—' }}</small></td>
                                        <td><small>{{ $history->delayOwner->name ?? '—' }}</small></td>
                                        <td><span class="badge bg-label-secondary">{{ ucfirst($history->source ?? 'manual') }}</span></td>
                                        <td><small>{{ $history->changedByAdmin->name ?? 'System' }}</small></td>
                                        <td><small class="text-muted">{{ $history->reason ?: '—' }}</small></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-5 text-muted">No delay ownership changes recorded.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- 9. Timeline --}}
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

    {{-- 10. Billing --}}
    @if ($activeTab === 'billing')
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0">Billing Readiness</h5>
                <small class="text-muted">Approved cases and missing fields required before billing handoff</small>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Case #</th>
                            <th>Payer</th>
                            <th>Practice</th>
                            <th>Approval Date</th>
                            <th>Effective Date</th>
                            <th>Payer Provider ID</th>
                            <th>Missing Fields</th>
                            <th>Ready to Bill</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($billingCases as $case)
                            @php
                                $missing = $billingService->missingFields($case);
                                $ready = count($missing) === 0;
                            @endphp
                            <tr>
                                <td class="fw-semibold">{{ $case->case_number }}</td>
                                <td>{{ $case->payer->name ?? 'N/A' }}</td>
                                <td><small>{{ $case->practice->legal_name ?? 'N/A' }}</small></td>
                                <td><small>{{ $case->approval_date?->format('m/d/Y') ?: '—' }}</small></td>
                                <td><small>{{ $case->effective_date?->format('m/d/Y') ?: '—' }}</small></td>
                                <td><small>{{ $case->payer_provider_id ?: '—' }}</small></td>
                                <td>
                                    @if ($missing)
                                        @foreach ($missing as $field)
                                            <span class="badge bg-label-warning me-1 mb-1">{{ $billingFieldLabels[$field] ?? $field }}</span>
                                        @endforeach
                                    @else
                                        <span class="badge bg-label-success">Complete</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-label-{{ $ready || $case->ready_to_bill ? 'success' : 'secondary' }}">
                                        {{ $ready || $case->ready_to_bill ? 'Yes' : 'No' }}
                                    </span>
                                    @if ($case->billing_notified)
                                        <small class="d-block text-muted mt-1">Billing notified</small>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">No approved cases eligible for billing review.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
