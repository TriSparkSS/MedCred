<div class="container-fluid px-3 px-md-4 py-4">
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

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-start gap-3">
            <div class="d-flex align-items-center gap-3">
                <span class="avatar rounded-circle bg-primary text-white fw-bold"
                    style="width: 48px; height: 48px; display: flex; align-items: center; justify-content: center;">
                    {{ substr($practice->legal_name ?? 'P', 0, 1) }}
                </span>
                <div>
                    <h4 class="fw-bold text-primary mb-1">{{ $practice->legal_name }}</h4>
                    <p class="text-muted mb-0">{{ $practice->dba_name ?: 'No DBA name on file' }}</p>
                </div>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('admin.practices.edit', $practice->id) }}" class="btn btn-primary">
                    <i class="ti tabler-edit me-1"></i> Edit
                </a>
                <a href="{{ route('admin.practices') }}" class="btn btn-outline-secondary">Back to list</a>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-4">
                        <div>
                            <h5 class="mb-1">Practice Details</h5>
                            <p class="text-muted mb-0">Business identifiers and contact information.</p>
                        </div>
                        <span class="badge bg-label-{{ $statusBadge }} text-uppercase">{{ strtoupper($statusKey ?? 'N/A') }}</span>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <small class="text-muted d-block">EIN/TIN</small>
                            <span class="fw-semibold">{{ $practice->ein_tin ?: 'N/A' }}</span>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted d-block">Group NPI</small>
                            <span class="fw-semibold">{{ $practice->group_npi ?: 'N/A' }}</span>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted d-block">Taxonomy Code</small>
                            <span class="fw-semibold">{{ $practice->taxonomy_code ?: 'N/A' }}</span>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted d-block">Login User</small>
                            <span class="fw-semibold">{{ $practice->user->email ?? 'N/A' }}</span>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted d-block">Phone</small>
                            <span class="fw-semibold">{{ $practice->phone ?: 'N/A' }}</span>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted d-block">Fax</small>
                            <span class="fw-semibold">{{ $practice->fax ?: 'N/A' }}</span>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted d-block">Email</small>
                            <span class="fw-semibold">{{ $practice->email ?: 'N/A' }}</span>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted d-block">Website</small>
                            @if ($practice->website)
                                <a href="{{ $practice->website }}" target="_blank" class="fw-semibold">{{ $practice->website }}</a>
                            @else
                                <span class="fw-semibold">N/A</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h5 class="mb-3">Addresses</h5>
                    @forelse($practice->addresses as $address)
                        <div class="border-bottom pb-3 mb-3">
                            <div class="d-flex justify-content-between gap-2 mb-2">
                                <div class="fw-semibold">{{ $address->location_name ?: 'Primary Location' }}</div>
                                <span class="badge bg-label-{{ $address->status === 'active' ? 'success' : 'secondary' }} text-uppercase">
                                    {{ strtoupper($address->status) }}
                                </span>
                            </div>
                            <p class="text-muted mb-1">{{ $address->address1 }}</p>
                            @if ($address->address2)
                                <p class="text-muted mb-1">{{ $address->address2 }}</p>
                            @endif
                            <p class="text-muted mb-1">{{ $address->city }}, {{ $address->state }} {{ $address->zip_code }}</p>
                            <p class="text-muted mb-2">{{ $address->country }}</p>
                            <small class="text-muted d-block">Phone: {{ $address->phone ?: 'N/A' }}</small>
                            <small class="text-muted d-block">Fax: {{ $address->fax ?: 'N/A' }}</small>
                        </div>
                    @empty
                        <p class="text-muted mb-0">No addresses found.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
