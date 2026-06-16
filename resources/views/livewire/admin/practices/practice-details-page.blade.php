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

    <div class="card shadow-sm border-0 mt-4">
        <div class="card-body pb-0">
            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 mb-4">
                <div>
                    <h5 class="mb-1">Assigned Providers</h5>
                    <p class="text-muted mb-0">All providers linked to this practice.</p>
                </div>
                <a href="{{ route('admin.provider-practices') }}" class="btn btn-outline-primary">
                    <i class="ti tabler-link me-1"></i> Manage Assignments
                </a>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Provider</th>
                        <th>NPI</th>
                        <th>Specialty</th>
                        <th>Primary</th>
                        <th>Dates</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($practice->providers as $provider)
                        <tr>
                            <td>
                                <a href="{{ route('admin.providers.show', $provider->id) }}" class="fw-semibold">
                                    {{ $provider->user->name ?? 'N/A' }}
                                </a>
                                <small class="text-muted d-block">{{ $provider->user->email ?? 'No email' }}</small>
                            </td>
                            <td><small class="text-muted">{{ $provider->npi ?: 'N/A' }}</small></td>
                            <td><small class="text-muted">{{ $provider->specialty->name ?? 'N/A' }}</small></td>
                            <td>
                                <span class="badge bg-label-{{ $provider->pivot->primary_flag ? 'success' : 'secondary' }}">
                                    {{ $provider->pivot->primary_flag ? 'Primary' : 'Secondary' }}
                                </span>
                            </td>
                            <td>
                                <small class="text-muted">
                                    {{ $provider->pivot->start_date ? \Illuminate\Support\Carbon::parse($provider->pivot->start_date)->format('m/d/Y') : 'N/A' }}
                                    -
                                    {{ $provider->pivot->end_date ? \Illuminate\Support\Carbon::parse($provider->pivot->end_date)->format('m/d/Y') : 'Present' }}
                                </small>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-5">
                                <i class="ti tabler-user-off d-block mb-2" style="font-size: 2rem; color: #ccc;"></i>
                                <p class="text-muted mb-0">No providers assigned</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
