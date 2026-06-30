<div>
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

    <div class="container-fluid flex-grow-1 px-3 px-md-4 py-3 py-md-4">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-start gap-3">
                <div class="d-flex align-items-center gap-3">
                    <span class="avatar rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center"
                        style="width: 52px; height: 52px; font-size: 1.25rem;">
                        {{ strtoupper(substr($provider->user->name ?? 'P', 0, 1)) }}
                    </span>
                    <div>
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                            <h4 class="fw-bold text-primary mb-0">{{ $provider->user->name }}</h4>
                            <span class="badge bg-label-{{ $statusBadge }} text-uppercase">{{ strtoupper($statusKey ?? 'N/A') }}</span>
                        </div>
                        <p class="text-muted mb-0">
                            {{ $provider->specialty->name ?? 'No specialty' }}
                            · NPI: {{ $provider->npi ?: 'N/A' }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-sm-4">
                <x-provider.stat-card label="NPI" :value="$provider->npi ?: '—'" />
            </div>
            <div class="col-sm-4">
                <x-provider.stat-card label="Specialty" :value="$provider->specialty->name ?? '—'" />
            </div>
            <div class="col-sm-4">
                <x-provider.stat-card label="Active Applications" :value="$activeCases ?? 0" valueClass="text-primary" />
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom">
                <h6 class="fw-semibold mb-0">Provider Information</h6>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-md-4">
                        <small class="text-muted d-block mb-1">Portal Login</small>
                        <span class="fw-semibold">{{ $provider->user->email ?? 'N/A' }}</span>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block mb-1">Phone</small>
                        <span class="fw-semibold">{{ $provider->user->phone ?? 'N/A' }}</span>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block mb-1">CAQH ID</small>
                        <span class="fw-semibold">{{ $provider->caqh_id ?: 'N/A' }}</span>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block mb-1">License</small>
                        <span class="fw-semibold">
                            {{ $provider->license_number ? $provider->license_number . ($provider->license_state ? " ({$provider->license_state})" : '') : 'N/A' }}
                        </span>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block mb-1">DEA</small>
                        <span class="fw-semibold">{{ $provider->dea ?: 'N/A' }}</span>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block mb-1">Address</small>
                        <span class="fw-semibold">
                            {{ $provider->address ?: 'N/A' }}{{ $provider->city ? ', ' . $provider->city : '' }}{{ $provider->state ? ', ' . $provider->state : '' }} {{ $provider->zip }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
