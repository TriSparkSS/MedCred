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

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-start gap-3">
            <div class="d-flex align-items-center gap-3">
                <span class="avatar rounded-circle bg-primary text-white fw-bold"
                    style="width: 48px; height: 48px; display: flex; align-items: center; justify-content: center;">
                    {{ substr($provider->user->name ?? 'P', 0, 1) }}
                </span>
                <div>
                    <h4 class="fw-bold text-primary mb-1">{{ $provider->user->name ?? 'N/A' }}</h4>
                    <p class="text-muted mb-0">{{ $provider->specialty->name ?? 'No Specialty' }}</p>
                </div>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('admin.provider-practices') }}" class="btn btn-outline-primary">
                    <i class="ti tabler-link me-1"></i> Assign Practice
                </a>
                <a href="{{ route('admin.providers.edit', $provider->id) }}" class="btn btn-primary">
                    <i class="ti tabler-edit me-1"></i> Edit
                </a>
                <a href="{{ route('admin.providers') }}" class="btn btn-outline-secondary">Back to list</a>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-4">
                        <div>
                            <h5 class="mb-1">Provider Details</h5>
                            <p class="text-muted mb-0">Profile and credentialing identifiers.</p>
                        </div>
                        <span class="badge bg-label-{{ $statusBadge }} text-uppercase">{{ strtoupper($statusKey ?? 'N/A') }}</span>
                    </div>

                    <div class="mb-3">
                        <small class="text-muted d-block">Email</small>
                        <span class="fw-semibold">{{ $provider->user->email ?? 'N/A' }}</span>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted d-block">Phone</small>
                        <span class="fw-semibold">{{ $provider->user->phone ?? 'N/A' }}</span>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted d-block">NPI</small>
                        <span class="fw-semibold">{{ $provider->npi ?: 'N/A' }}</span>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted d-block">Practice Text</small>
                        <span class="fw-semibold">{{ $provider->practice ?: 'N/A' }}</span>
                    </div>
                    <div>
                        <small class="text-muted d-block">Address</small>
                        <span class="fw-semibold">
                            {{ $provider->address ?: 'N/A' }}{{ $provider->city ? ', ' . $provider->city : '' }}{{ $provider->state ? ', ' . $provider->state : '' }} {{ $provider->zip }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body pb-0">
                    <h5 class="mb-1">Assigned Practices</h5>
                    <p class="text-muted mb-4">All practices linked to this provider.</p>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Practice</th>
                                <th>Group NPI</th>
                                <th>Location</th>
                                <th>Primary</th>
                                <th>Dates</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($provider->practices as $practice)
                                <tr>
                                    <td>
                                        <a href="{{ route('admin.practices.show', $practice->id) }}" class="fw-semibold">
                                            {{ $practice->legal_name }}
                                        </a>
                                        <small class="text-muted d-block">{{ $practice->dba_name ?: 'No DBA' }}</small>
                                    </td>
                                    <td><small class="text-muted">{{ $practice->group_npi ?: 'N/A' }}</small></td>
                                    <td>
                                        <small class="text-muted">
                                            {{ $practice->primaryAddress?->city ?: 'N/A' }}{{ $practice->primaryAddress?->state ? ', ' . $practice->primaryAddress->state : '' }}
                                        </small>
                                    </td>
                                    <td>
                                        <span class="badge bg-label-{{ $practice->pivot->primary_flag ? 'success' : 'secondary' }}">
                                            {{ $practice->pivot->primary_flag ? 'Primary' : 'Secondary' }}
                                        </span>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            {{ $practice->pivot->start_date ? \Illuminate\Support\Carbon::parse($practice->pivot->start_date)->format('m/d/Y') : 'N/A' }}
                                            -
                                            {{ $practice->pivot->end_date ? \Illuminate\Support\Carbon::parse($practice->pivot->end_date)->format('m/d/Y') : 'Present' }}
                                        </small>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-5">
                                        <i class="ti tabler-building-off d-block mb-2" style="font-size: 2rem; color: #ccc;"></i>
                                        <p class="text-muted mb-0">No practices assigned</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
