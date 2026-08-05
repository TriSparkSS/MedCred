<div>
    <div class="container-fluid flex-grow-1 px-3 px-md-4 py-3 py-md-4">
        @php
            use App\Enums\PracticeStatus;

            $statusKey = is_object($practice->status) ? $practice->status->value : $practice->status;
            $statusBadge = match ($statusKey) {
                PracticeStatus::ACTIVE->value => 'success',
                PracticeStatus::PENDING->value => 'warning',
                PracticeStatus::INACTIVE->value => 'secondary',
                default => 'secondary',
            };
            $addressLabels = [
                'primary' => 'Primary Location',
                'alternative' => 'Alternative Location',
                'mailing' => 'Mailing Address',
                'billing' => 'Billing Address',
            ];
            $website = $practice->website;
            if ($website && !str_starts_with($website, 'http://') && !str_starts_with($website, 'https://')) {
                $website = 'https://' . $website;
            }
        @endphp

        {{-- Header --}}
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-start gap-3">
                <div class="d-flex align-items-center gap-3">
                    <span
                        class="avatar rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center"
                        style="width: 52px; height: 52px; font-size: 1.25rem;">
                        {{ strtoupper(substr($practice->legal_name ?? 'P', 0, 1)) }}
                    </span>
                    <div>
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                            <h4 class="fw-bold text-primary mb-0">{{ $practice->legal_name }}</h4>
                            <span
                                class="badge bg-label-{{ $statusBadge }} text-uppercase">{{ strtoupper($statusKey ?? 'N/A') }}</span>
                        </div>
                        <p class="text-muted mb-0">
                            {{ $practice->dba_name ?: 'No DBA on file' }}
                            · Group NPI: {{ $practice->group_npi ?: 'N/A' }}
                            · {{ $practice->email ?: 'No email' }}
                        </p>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('admin.credentials.create') }}?practice={{ $practice->id }}"
                        class="btn btn-outline-primary">
                        <i class="ti tabler-plus me-1"></i> New Application
                    </a>
                    <a href="{{ route('admin.practices.edit', $practice->id) }}" class="btn btn-primary">
                        <i class="ti tabler-edit me-1"></i> Edit
                    </a>
                    <a href="{{ route('admin.practices') }}" class="btn btn-outline-secondary">Back</a>
                </div>
            </div>
        </div>

        {{-- KPI Stats --}}
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-xl-3">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <p class="text-uppercase text-muted small mb-1">Linked Providers</p>
                        <h3 class="fw-bold mb-0">{{ $stats['linked_providers'] }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <p class="text-uppercase text-muted small mb-1">Active Applications</p>
                        <h3 class="fw-bold mb-0 text-primary">{{ $stats['active_cases'] }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <p class="text-uppercase text-muted small mb-1">Facility Locations</p>
                        <h3 class="fw-bold mb-0">{{ $stats['locations'] }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <p class="text-uppercase text-muted small mb-1">Contacts</p>
                        <h3 class="fw-bold mb-0">{{ $stats['contacts'] }}</h3>
                    </div>
                </div>
            </div>
        </div>

        {{-- Tabs --}}
        <ul class="nav nav-tabs mb-4 flex-nowrap overflow-auto">
            <li class="nav-item">
                <button type="button" class="nav-link {{ $activeTab === 'overview' ? 'active' : '' }}"
                    wire:click="setTab('overview')">Overview</button>
            </li>
            <li class="nav-item">
                <button type="button" class="nav-link {{ $activeTab === 'credentialing' ? 'active' : '' }}"
                    wire:click="setTab('credentialing')">
                    Credentialing
                    @if ($practice->credentialingCases->count())
                        <span class="badge bg-primary ms-1">{{ $practice->credentialingCases->count() }}</span>
                    @endif
                </button>
            </li>
            <li class="nav-item">
                <button type="button" class="nav-link {{ $activeTab === 'locations' ? 'active' : '' }}"
                    wire:click="setTab('locations')">Locations & Contacts</button>
            </li>
            <li class="nav-item">
                <button type="button" class="nav-link {{ $activeTab === 'providers' ? 'active' : '' }}"
                    wire:click="setTab('providers')">
                    Providers
                    @if ($practice->providers->count())
                        <span class="badge bg-label-primary ms-1">{{ $practice->providers->count() }}</span>
                    @endif
                </button>
            </li>
            <li class="nav-item">
                <button type="button" class="nav-link {{ $activeTab === 'timeline' ? 'active' : '' }}"
                    wire:click="setTab('timeline')">Timeline</button>
            </li>
            <li class="nav-item">
                <button type="button" class="nav-link {{ $activeTab === 'documents' ? 'active' : '' }}"
                    wire:click="setTab('documents')">Documents</button>
            </li>
        </ul>

        {{-- Overview Tab --}}
        @if ($activeTab === 'overview')
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-white border-bottom">
                            <h6 class="mb-0 fw-semibold">Practice Information</h6>
                            <small class="text-muted">Business identifiers and contact details</small>
                        </div>
                        <div class="card-body">
                            <div class="row g-4">
                                <div class="col-md-4">
                                    <small class="text-muted d-block mb-1">Legal Name</small>
                                    <span class="fw-semibold">{{ $practice->legal_name ?: 'N/A' }}</span>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted d-block mb-1">DBA Name</small>
                                    <span class="fw-semibold">{{ $practice->dba_name ?: 'N/A' }}</span>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted d-block mb-1">Status</small>
                                    <span
                                        class="badge bg-label-{{ $statusBadge }} text-uppercase">{{ strtoupper($statusKey ?? 'N/A') }}</span>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted d-block mb-1">EIN / TIN</small>
                                    <span class="fw-semibold">{{ $practice->ein_tin ?: 'N/A' }}</span>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted d-block mb-1">Group NPI</small>
                                    <span class="fw-semibold">{{ $practice->group_npi ?: 'N/A' }}</span>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted d-block mb-1">Taxonomy Code</small>
                                    <span class="fw-semibold">{{ $practice->taxonomy_code ?: 'N/A' }}</span>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted d-block mb-1">License Number</small>
                                    <span class="fw-semibold">{{ $practice->license_number ?: 'N/A' }}</span>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted d-block mb-1">Portal Login</small>
                                    <span class="fw-semibold">{{ $practice->user->email ?? 'N/A' }}</span>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted d-block mb-1">Practice Email</small>
                                    <span class="fw-semibold">{{ $practice->email ?: 'N/A' }}</span>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted d-block mb-1">Phone</small>
                                    <span class="fw-semibold">{{ $practice->phone ?: 'N/A' }}</span>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted d-block mb-1">Fax</small>
                                    <span class="fw-semibold">{{ $practice->fax ?: 'N/A' }}</span>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted d-block mb-1">Website</small>
                                    @if ($website)
                                        <a href="{{ $website }}" target="_blank" rel="noopener"
                                            class="fw-semibold text-break">{{ $practice->website }}</a>
                                    @else
                                        <span class="fw-semibold">N/A</span>
                                    @endif
                                </div>
                                @if ($practice->document_path)
                                    <div class="col-md-4">
                                        <small class="text-muted d-block mb-1">Uploaded Document</small>
                                        <a href="{{ asset('storage/' . $practice->document_path) }}" target="_blank"
                                            class="fw-semibold">
                                            <i
                                                class="ti tabler-file me-1"></i>{{ $practice->document_original_name ?: 'View file' }}
                                        </a>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-header bg-white border-bottom">
                            <h6 class="mb-0 fw-semibold">Bank Details</h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <small class="text-muted d-block mb-1">Bank Name</small>
                                <span class="fw-semibold">{{ $practice->bank_name ?: 'N/A' }}</span>
                            </div>
                            <div class="mb-3">
                                <small class="text-muted d-block mb-1">Account Number</small>
                                <span class="fw-semibold">{{ $practice->bank_account ?: 'N/A' }}</span>
                            </div>
                            <div class="mb-3">
                                <small class="text-muted d-block mb-1">Routing Number</small>
                                <span class="fw-semibold">{{ $practice->bank_routing_number ?: 'N/A' }}</span>
                            </div>
                            <div class="mb-3">
                                <small class="text-muted d-block mb-1">Bank Phone</small>
                                <span class="fw-semibold">{{ $practice->bank_phone ?: 'N/A' }}</span>
                            </div>
                            <div>
                                <small class="text-muted d-block mb-1">Bank Address</small>
                                <span class="fw-semibold">{{ $practice->bank_address ?: 'N/A' }}</span>
                            </div>
                        </div>
                    </div>

                    @if ($practice->addresses->isNotEmpty())
                        <div class="card shadow-sm border-0">
                            <div
                                class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                                <h6 class="mb-0 fw-semibold">Primary Address</h6>
                                <button type="button" class="btn btn-sm btn-link p-0"
                                    wire:click="setTab('locations')">View all</button>
                            </div>
                            <div class="card-body">
                                @php $primaryAddress = $practice->addresses->firstWhere('type', 'primary') ?? $practice->addresses->first(); @endphp
                                <div class="fw-semibold mb-2">{{ $addressLabels[$primaryAddress->type] ?? 'Address' }}
                                </div>
                                @if ($primaryAddress->location_name)
                                    <p class="text-muted mb-1">{{ $primaryAddress->location_name }}</p>
                                @endif
                                <p class="text-muted mb-1">{{ $primaryAddress->address1 }}</p>
                                @if ($primaryAddress->address2)
                                    <p class="text-muted mb-1">{{ $primaryAddress->address2 }}</p>
                                @endif
                                <p class="text-muted mb-0">
                                    {{ $primaryAddress->city }}, {{ $primaryAddress->state }}
                                    {{ $primaryAddress->zip_code }}
                                </p>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        {{-- Credentialing Tab --}}
        @if ($activeTab === 'credentialing')
            <div class="card shadow-sm border-0">
                <div
                    class="card-header bg-white d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2">
                    <div>
                        <h6 class="mb-0 fw-semibold">Payer Applications</h6>
                        <small class="text-muted">Credentialing cases for this practice</small>
                    </div>
                    <a href="{{ route('admin.credentials.create') }}?practice={{ $practice->id }}"
                        class="btn btn-sm btn-primary">
                        <i class="ti tabler-plus me-1"></i> New Application
                    </a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Case #</th>
                                <th>Provider</th>
                                <th>Payer</th>
                                <th>Status</th>
                                <th>Delay Owner</th>
                                <th>Assigned To</th>
                                <th>Next Follow-up</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($practice->credentialingCases as $case)
                                <tr>
                                    <td><a href="{{ route('admin.credentials') }}"
                                            class="fw-semibold">{{ $case->case_number }}</a></td>
                                    <td>{{ $case->provider->user->name ?? 'N/A' }}</td>
                                    <td>{{ $case->payer->name ?? 'N/A' }}</td>
                                    <td><span class="badge bg-label-info">{{ $case->status->name ?? 'N/A' }}</span>
                                    </td>
                                    <td><small>{{ $case->delayOwner->name ?? '—' }}</small></td>
                                    <td><small>{{ $case->assignedAdmin->name ?? 'Unassigned' }}</small></td>
                                    <td><small
                                            class="{{ $case->isOverdue() ? 'text-danger fw-bold' : '' }}">{{ $case->next_follow_up_date?->format('m/d/Y') ?: '—' }}</small>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        <i class="ti tabler-briefcase-off d-block mb-2" style="font-size: 2rem;"></i>
                                        No credentialing applications for this practice yet.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- Locations & Contacts Tab --}}
        @if ($activeTab === 'locations')
            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-white border-bottom">
                            <h6 class="mb-0 fw-semibold">Addresses</h6>
                        </div>
                        <div class="card-body">
                            @forelse($practice->addresses as $address)
                                <div class="border rounded p-3 mb-3 {{ $loop->last ? 'mb-0' : '' }}">
                                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                        <span
                                            class="fw-semibold">{{ $addressLabels[$address->type] ?? ($address->location_name ?: 'Address') }}</span>
                                        @if ($address->type === 'primary')
                                            <span
                                                class="badge bg-label-{{ $address->status === 'active' ? 'success' : 'secondary' }} text-uppercase">{{ strtoupper($address->status) }}</span>
                                        @endif
                                    </div>
                                    @if ($address->location_name)
                                        <p class="text-muted mb-1 small">{{ $address->location_name }}</p>
                                    @endif
                                    <p class="mb-1">{{ $address->address1 }}</p>
                                    @if ($address->address2)
                                        <p class="mb-1 text-muted">{{ $address->address2 }}</p>
                                    @endif
                                    <p class="mb-2">{{ $address->city }}, {{ $address->state }}
                                        {{ $address->zip_code }}@if ($address->county)
                                            · {{ $address->county }} County
                                        @endif
                                    </p>
                                    <small class="text-muted d-block">Phone: {{ $address->phone ?: 'N/A' }} · Fax:
                                        {{ $address->fax ?: 'N/A' }}</small>
                                </div>
                            @empty
                                <p class="text-muted mb-0">No addresses on file.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-header bg-white border-bottom">
                            <h6 class="mb-0 fw-semibold">Facility Locations</h6>
                        </div>
                        <div class="card-body">
                            @forelse($practice->locations as $location)
                                <div class="border rounded p-3 mb-3 {{ $loop->last ? 'mb-0' : '' }}">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <span class="fw-semibold">{{ $location->name ?: 'Unnamed location' }}</span>
                                        @if ($location->is_primary)
                                            <span class="badge bg-label-success">Primary</span>
                                        @endif
                                    </div>
                                    <p class="mb-1">{{ $location->address1 }}, {{ $location->city }},
                                        {{ $location->state }} {{ $location->zip_code }}</p>
                                    @if ($location->npi)
                                        <small class="text-muted d-block">NPI: {{ $location->npi }}</small>
                                    @endif
                                </div>
                            @empty
                                <p class="text-muted mb-0">No facility locations on file.</p>
                            @endforelse
                        </div>
                    </div>
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white border-bottom">
                            <h6 class="mb-0 fw-semibold">Contacts</h6>
                        </div>
                        <div class="card-body">
                            @forelse($practice->contacts as $contact)
                                <div class="border rounded p-3 mb-3 {{ $loop->last ? 'mb-0' : '' }}">
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <span class="fw-semibold">{{ $contact->name }}</span>
                                        @if ($contact->is_primary)
                                            <span class="badge bg-label-success">Primary</span>
                                        @endif
                                    </div>
                                    <small class="text-muted d-block">{{ $contact->title ?: 'No title' }}</small>
                                    <small class="text-muted d-block">{{ $contact->email ?: 'No email' }}</small>
                                    <small class="text-muted d-block">{{ $contact->phone ?: 'No phone' }}</small>
                                </div>
                            @empty
                                <p class="text-muted mb-0">No contacts on file.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- Providers Tab --}}
        @if ($activeTab === 'providers')
            <div class="card shadow-sm border-0">
                <div
                    class="card-header bg-white d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2">
                    <div>
                        <h6 class="mb-0 fw-semibold">Assigned Providers</h6>
                        <small class="text-muted">Providers linked to this practice</small>
                    </div>
                    <a href="{{ route('admin.providers.create') }}" class="btn btn-sm btn-outline-primary">
                        <i class="ti tabler-link me-1"></i> Add A Provider
                    </a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Provider</th>
                                <th>NPI</th>
                                <th>Specialty</th>
                                <th>Primary</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($practice->providers as $provider)
                                <tr>
                                    <td>
                                        <a href="{{ route('admin.providers.show', $provider->id) }}"
                                            class="fw-semibold">{{ $provider->user->name ?? 'N/A' }}</a>
                                        <small class="text-muted d-block">{{ $provider->user->email ?? '' }}</small>
                                    </td>
                                    <td><small>{{ $provider->npi ?: 'N/A' }}</small></td>
                                    <td><small>{{ $provider->specialty->name ?? 'N/A' }}</small></td>
                                    <td>
                                        <span
                                            class="badge bg-label-{{ $provider->pivot->primary_flag ? 'success' : 'secondary' }}">
                                            {{ $provider->pivot->primary_flag ? 'Primary' : 'Secondary' }}
                                        </span>
                                    </td>
                                    <td><small>{{ $provider->pivot->start_date ? \Illuminate\Support\Carbon::parse($provider->pivot->start_date)->format('m/d/Y') : 'N/A' }}</small>
                                    </td>
                                    <td><small>{{ $provider->pivot->end_date ? \Illuminate\Support\Carbon::parse($provider->pivot->end_date)->format('m/d/Y') : 'Present' }}</small>
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <a href="{{ route('admin.providers.show', ['provider' => $provider->id, 'tab' => 'documents']) }}"
                                            class="btn btn-sm btn-outline-primary"
                                            title="View provider documents">
                                            <i class="ti tabler-files me-1"></i>Documents
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        <i class="ti tabler-user-off d-block mb-2" style="font-size: 2rem;"></i>
                                        No providers assigned to this practice.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- Timeline Tab --}}
        @if ($activeTab === 'timeline')
            <div class="card shadow-sm border-0">
                <div
                    class="card-header bg-white d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2">
                    <div>
                        <h6 class="mb-0 fw-semibold">Activity Timeline</h6>
                        <small class="text-muted">Recent activity across all practice credentialing cases</small>   
                    </div>
                    <a href="{{ route('admin.tasks.kanban') }}" class="btn btn-sm btn-outline-primary">
                        <i class="ti tabler-link me-1"></i> Add A Task
                    </a>
                </div>
                
                <div class="card-body px-4 py-4">
                    <x-admin.activity-timeline :activities="$timelineActivities" :show-case="true" />
                </div>
            </div>
        @endif

        {{-- Documents Tab --}}
        @if ($activeTab === 'documents')
            <div class="card shadow-sm border-0">
                <div
                    class="card-header bg-white d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2">
                    <div>
                        <h6 class="mb-0 fw-semibold">Practice Documents</h6>
                        <small class="text-muted">Files linked to this practice</small>
                    </div>
                    <a href="{{ route('admin.documents') }}" class="btn btn-sm btn-primary">
                        <i class="ti tabler-upload me-1"></i> Document Library
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
                            @forelse($practice->documents as $document)
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
                                @if ($practice->document_path)
                                    <tr>
                                        <td class="fw-semibold">
                                            {{ $practice->document_original_name ?: 'Practice document' }}</td>
                                        <td>—</td>
                                        <td>—</td>
                                        <td>—</td>
                                        <td><span class="badge bg-label-success">Active</span></td>
                                        <td class="text-end">
                                            <a href="{{ asset('storage/' . $practice->document_path) }}"
                                                target="_blank" class="btn btn-sm btn-outline-secondary">
                                                <i class="ti tabler-download"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @else
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">No documents on file
                                            for this practice.</td>
                                    </tr>
                                @endif
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</div>
