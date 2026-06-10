<div>
    @php
        use App\Enums\ProviderStatus;
    @endphp
    <div class="container-fluid flex-grow-1 px-3 px-md-4 py-3 py-md-4">
        <!-- Header Section -->
        <div class="row g-4 mb-5">
            <div class="col-12">
                <div class="card shadow-sm border-0">
                    <div
                        class="card-body d-flex flex-column flex-md-row justify-content-between align-items-start gap-3">
                        <div>
                            <h3 class="fw-bold text-primary mb-2">Provider Management</h3>
                            <p class="text-muted mb-0">Oversee and maintain credentialing compliance across your network.
                            </p>
                        </div>
                        <div class="d-flex flex-column flex-sm-row gap-2">
                            <a href="{{ route('admin.providers.create') }}" class="btn btn-primary">
                                <i class="ti tabler-plus me-1"></i> New Provider
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Cards Section -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-md-3">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <p class="text-uppercase text-muted small mb-1">Total Providers</p>
                            </div>
                            <span class="badge bg-label-success">Active</span>
                        </div>
                        <h3 class="fw-bold mb-0">{{ $stats['total'] ?? 0 }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-md-3">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <p class="text-uppercase text-muted small mb-1">Approved</p>
                            </div>
                            <span class="badge bg-label-info">Status</span>
                        </div>
                        <h3 class="fw-bold mb-0">{{ $stats['active'] ?? 0 }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-md-3">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <p class="text-uppercase text-muted small mb-1">Pending</p>
                            </div>
                            <span class="badge bg-label-warning">In Progress</span>
                        </div>
                        <h3 class="fw-bold mb-0">{{ $stats['pending'] ?? 0 }}</h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search and Filter Section -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <div class="row g-3 align-items-center">
                    <div class="col-md-6">
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0"><i class="ti tabler-search"></i></span>
                            <input type="search" wire:model.live="search" class="form-control border-start-0"
                                placeholder="Search by provider name, NPI, practice or city...">
                        </div>
                    </div>

                    <div class="col-md-3">
                        <select wire:model.live="filterSpecialty" class="form-select">
                            <option value="">All Specialties</option>
                            @foreach ($specialties as $spec)
                                <option value="{{ $spec->id }}">{{ $spec->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3 d-flex gap-2 justify-content-end">
                        <select wire:model.live="filterStatus" class="form-select me-2">
                            <option value="">All Statuses</option>
                            <option value="{{ ProviderStatus::PENDING->value }}">Pending</option>
                            <option value="{{ ProviderStatus::APPROVED->value }}">Approved</option>
                            <option value="{{ ProviderStatus::REJECTED->value }}">Rejected</option>
                        </select>

                        @if ($search || $filterSpecialty || $filterStatus)
                            <button wire:click="$set('search',''); $set('filterSpecialty',''); $set('filterStatus','');"
                                class="btn btn-outline-secondary btn-sm">
                                <i class="ti tabler-x me-1"></i> Clear
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Table Section -->
        <div class="card shadow-sm border-0">
            <div class="card-body pb-0">
                <div
                    class="d-flex flex-column flex-sm-row align-items-start align-items-sm-center justify-content-between gap-3 mb-4">
                    <div>
                        <h5 class="mb-1">Providers List</h5>
                        <p class="text-muted mb-0">{{ $providers->total() }} total providers</p>
                    </div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col" style="width: 40px;">
                                <input class="form-check-input" type="checkbox" />
                            </th>
                            <th scope="col">Provider Name</th>
                            <th scope="col">NPI</th>
                            <th scope="col">Specialty</th>
                            <th scope="col">Practice</th>
                            <th scope="col">City</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($providers as $provider)
                            <tr>
                                <td><input class="form-check-input" type="checkbox" /></td>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <span class="avatar rounded-circle bg-primary text-white fw-bold"
                                            style="width: 36px; height: 36px; display: flex; align-items: center; justify-content: center;">
                                            {{ substr($provider->user->name ?? 'P', 0, 1) }}
                                        </span>
                                        <div>
                                            <div class="fw-semibold">{{ $provider->user->name ?? 'N/A' }}</div>
                                            <small class="text-muted">{{ $provider->specialty->name ?? 'No Specialty' }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td> <small class="text-muted">{{ $provider->npi ?? 'N/A' }}</small> </td>
                                <td> <small class="text-muted">{{ $provider->specialty->name ?? 'N/A' }}</small> </td>
                                <td> <small class="text-muted">{{ Str::limit($provider->practice, 30) ?? 'N/A' }}</small> </td>
                                <td> <small class="text-muted">{{ $provider->city ?? 'N/A' }}</small> </td>
                                <td>
                                    @php
                                        $statusKey = is_object($provider->status)
                                            ? $provider->status->value
                                            : $provider->status;
                                        $statusBadge = match ($statusKey) {
                                            ProviderStatus::APPROVED->value => 'success',
                                            ProviderStatus::PENDING->value => 'warning',
                                            ProviderStatus::REJECTED->value => 'danger',
                                            default => 'secondary',
                                        };
                                    @endphp
                                    <span
                                        class="badge bg-label-{{ $statusBadge }} text-uppercase">{{ strtoupper($provider->status?->value ?? 'N/A') }}</span>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group" role="group">
                                        <a href="{{ route('admin.providers.edit', $provider->id) }}"
                                            class="btn btn-sm btn-outline-primary" title="Edit">
                                            <i class="ti tabler-edit"></i>
                                        </a>
                                        <button wire:click="delete({{ $provider->id }})"
                                            class="btn btn-sm btn-outline-danger" title="Delete">
                                            <i class="ti tabler-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-5">
                                    <div class="d-flex flex-column align-items-center gap-2">
                                        <i class="ti tabler-inbox" style="font-size: 2rem; color: #ccc;"></i>
                                        <p class="text-muted mb-0">No providers found</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <!-- Pagination -->
            <div class="card-body border-top">
                {{ $providers->links('livewire::bootstrap') }}
            </div>
        </div>
    </div>
</div>
