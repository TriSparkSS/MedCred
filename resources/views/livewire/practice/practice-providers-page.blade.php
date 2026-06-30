<div>
    <div class="container-fluid flex-grow-1 px-3 px-md-4 py-3 py-md-4">
        <x-practice.page-header
            title="Linked Providers"
            subtitle="Providers assigned to your practice and their credentialing activity."
        />

        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-md-4">
                <x-practice.stat-card label="Total Providers" :value="$practice->providers->count()" />
            </div>
            <div class="col-sm-6 col-md-4">
                <x-practice.stat-card label="Active Applications" :value="$activeCases ?? 0" valueClass="text-primary" />
            </div>
            <div class="col-sm-6 col-md-4">
                <x-practice.stat-card label="Approved Providers" :value="$approvedProviders ?? 0" valueClass="text-success" />
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Provider</th>
                            <th>NPI</th>
                            <th>Specialty</th>
                            <th>Status</th>
                            <th>Active Cases</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($practice->providers as $provider)
                            <tr wire:key="provider-{{ $provider->id }}">
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="avatar avatar-sm">
                                            <span class="avatar-initial rounded-circle bg-primary text-white">
                                                {{ strtoupper(substr($provider->user->name ?? 'P', 0, 1)) }}
                                            </span>
                                        </span>
                                        <span class="fw-semibold">{{ $provider->user->name ?? '—' }}</span>
                                    </div>
                                </td>
                                <td>{{ $provider->npi ?: '—' }}</td>
                                <td>{{ $provider->specialty->name ?? '—' }}</td>
                                <td>
                                    <span class="badge bg-label-{{ ($provider->status->value ?? $provider->status) === 'approved' ? 'success' : 'warning' }}">
                                        {{ ucfirst($provider->status->value ?? $provider->status) }}
                                    </span>
                                </td>
                                <td>{{ $provider->credentialingCases->where('practice_id', $practice->id)->count() }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-5">
                                    <i class="ti tabler-users-off d-block mb-2 fs-3"></i>
                                    No providers linked to this practice.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
