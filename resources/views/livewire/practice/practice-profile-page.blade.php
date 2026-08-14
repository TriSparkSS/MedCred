<div>
    @php
        use App\Enums\PracticeStatus;
        $statusKey = is_object($practice->status) ? $practice->status->value : $practice->status;
        $statusBadge = match ($statusKey) {
            PracticeStatus::ACTIVE->value => 'success',
            PracticeStatus::PENDING->value => 'warning',
            PracticeStatus::INACTIVE->value => 'secondary',
            default => 'secondary',
        };
    @endphp

    <div class="container-fluid flex-grow-1 px-3 px-md-4 py-3 py-md-4">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-start gap-3">
                <div class="d-flex align-items-center gap-3">
                    <span class="avatar rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center"
                        style="width: 52px; height: 52px; font-size: 1.25rem;">
                        {{ strtoupper(substr($practice->legal_name ?? 'P', 0, 1)) }}
                    </span>
                    <div>
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                            <h4 class="fw-bold text-primary mb-0">{{ $practice->legal_name }}</h4>
                            <span class="badge bg-label-{{ $statusBadge }} text-uppercase">{{ strtoupper($statusKey ?? 'N/A') }}</span>
                        </div>
                        <p class="text-muted mb-0">
                            {{ $practice->dba_name ?: 'No DBA on file' }}
                            · Group NPI: {{ $practice->group_npi ?: 'N/A' }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-sm-4">
                <x-practice.stat-card label="Group NPI" :value="$practice->group_npi ?: '—'" />
            </div>
            <div class="col-sm-4">
                <x-practice.stat-card label="Linked Providers" :value="$practice->providers->count()" />
            </div>
            <div class="col-sm-4">
                <x-practice.stat-card label="Active Applications" :value="$activeCases ?? 0" valueClass="text-primary" />
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white border-bottom">
                <h6 class="fw-semibold mb-0">Practice Information</h6>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-md-4">
                        <small class="text-muted d-block mb-1">Portal Login</small>
                        <span class="fw-semibold">{{ $practice->user->email ?? 'N/A' }}</span>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block mb-1">Contact Email</small>
                        <span class="fw-semibold">{{ $practice->email ?: 'N/A' }}</span>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block mb-1">Phone</small>
                        <span class="fw-semibold">{{ $practice->phone ?: 'N/A' }}</span>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block mb-1">EIN/TIN</small>
                        <span class="fw-semibold">{{ $practice->ein_tin ?: 'N/A' }}</span>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block mb-1">Taxonomy</small>
                        <span class="fw-semibold">{{ $practice->taxonomy_code ?: 'N/A' }}</span>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block mb-1">Website</small>
                        <span class="fw-semibold">{{ $practice->website ?: 'N/A' }}</span>
                    </div>
                </div>
            </div>
        </div>

        @if($canViewLocations && $practice->locations->isNotEmpty())
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white">
                <h6 class="fw-semibold mb-0">Locations</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr><th>Name</th><th>Address</th><th>Primary</th></tr>
                    </thead>
                    <tbody>
                        @foreach($practice->locations as $location)
                            <tr>
                                <td class="fw-semibold">{{ $location->name ?? '—' }}</td>
                                <td>{{ $location->address1 ?? '' }} {{ $location->city ?? '' }}, {{ $location->state ?? '' }} {{ $location->zip_code ?? '' }}</td>
                                <td>
                                    @if($location->is_primary)
                                        <span class="badge bg-label-success">Yes</span>
                                    @else
                                        <span class="text-muted">No</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        @if($practice->contacts->isNotEmpty())
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white">
                <h6 class="fw-semibold mb-0">Contacts</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr><th>Name</th><th>Role</th><th>Email</th><th>Phone</th></tr>
                    </thead>
                    <tbody>
                        @foreach($practice->contacts as $contact)
                            <tr>
                                <td class="fw-semibold">{{ $contact->name ?? '—' }}</td>
                                <td>{{ $contact->title ?? '—' }}</td>
                                <td>{{ $contact->email ?? '—' }}</td>
                                <td>{{ $contact->phone ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    </div>
</div>
