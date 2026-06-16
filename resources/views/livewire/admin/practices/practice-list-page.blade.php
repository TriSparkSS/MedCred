<div>
    @php
        use App\Enums\PracticeStatus;
    @endphp

    <div class="container-fluid flex-grow-1 px-3 px-md-4 py-3 py-md-4">
        <div class="row g-4 mb-5">
            <div class="col-12">
                <div class="card shadow-sm border-0">
                    <div
                        class="card-body d-flex flex-column flex-md-row justify-content-between align-items-start gap-3">
                        <div>
                            <h3 class="fw-bold text-primary mb-2">Practice Management</h3>
                            <p class="text-muted mb-0">Oversee and maintain credentialing compliance across your network.
                            </p>
                        </div>
                        <div>
                            <a href="{{ route('admin.practices.create') }}" class="btn btn-primary">
                                <i class="ti tabler-plus me-1"></i> New Practice
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-md-3">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <p class="text-uppercase text-muted small mb-1">Total Practices</p>
                        <h3 class="fw-bold mb-0">{{ $stats['total'] ?? 0 }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-md-3">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <p class="text-uppercase text-muted small mb-1">Active</p>
                        <h3 class="fw-bold mb-0">{{ $stats['active'] ?? 0 }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-md-3">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <p class="text-uppercase text-muted small mb-1">Pending</p>
                        <h3 class="fw-bold mb-0">{{ $stats['pending'] ?? 0 }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-md-3">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <p class="text-uppercase text-muted small mb-1">Inactive</p>
                        <h3 class="fw-bold mb-0">{{ $stats['inactive'] ?? 0 }}</h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <div class="row g-3 align-items-center">
                    <div class="col-md-8">
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0"><i class="ti tabler-search"></i></span>
                            <input type="search" wire:model.live="search" class="form-control border-start-0"
                                placeholder="Search by practice name, EIN/TIN, group NPI, taxonomy, email or location...">
                        </div>
                    </div>
                    <div class="col-md-4 d-flex gap-2">
                        <select wire:model.live="filterStatus" class="form-select">
                            <option value="">All Statuses</option>
                            <option value="{{ PracticeStatus::PENDING->value }}">Pending</option>
                            <option value="{{ PracticeStatus::ACTIVE->value }}">Active</option>
                            <option value="{{ PracticeStatus::INACTIVE->value }}">Inactive</option>
                        </select>
                        @if ($search || $filterStatus)
                            <button wire:click="$set('search',''); $set('filterStatus','');"
                                class="btn btn-outline-secondary btn-sm">
                                <i class="ti tabler-x me-1"></i> Clear
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body pb-0">
                <div
                    class="d-flex flex-column flex-sm-row align-items-start align-items-sm-center justify-content-between gap-3 mb-4">
                    <div>
                        <h5 class="mb-1">Practice List</h5>
                        <p class="text-muted mb-0">{{ $practices->total() }} total practices</p>
                    </div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Practice Name</th>
                            <th scope="col">EIN/TIN</th>
                            <th scope="col">Group NPI</th>
                            <th scope="col">Location</th>
                            <th scope="col">Phone</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($practices as $practice)
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <span class="avatar rounded-circle bg-primary text-white fw-bold"
                                            style="width: 36px; height: 36px; display: flex; align-items: center; justify-content: center;">
                                            {{ substr($practice->legal_name ?? 'P', 0, 1) }}
                                        </span>
                                        <div>
                                            <div class="fw-semibold">{{ $practice->legal_name }}</div>
                                            <small class="text-muted">{{ $practice->dba_name ?: 'No DBA' }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td><small class="text-muted">{{ $practice->ein_tin ?: 'N/A' }}</small></td>
                                <td><small class="text-muted">{{ $practice->group_npi ?: 'N/A' }}</small></td>
                                <td>
                                    <small class="text-muted">
                                        {{ $practice->primaryAddress?->city ?: 'N/A' }}{{ $practice->primaryAddress?->state ? ', ' . $practice->primaryAddress->state : '' }}
                                    </small>
                                </td>
                                <td><small class="text-muted">{{ $practice->phone ?: 'N/A' }}</small></td>
                                <td>
                                    @php
                                        $statusKey = is_object($practice->status)
                                            ? $practice->status->value
                                            : $practice->status;
                                        $statusBadge = match ($statusKey) {
                                            PracticeStatus::ACTIVE->value => 'success',
                                            PracticeStatus::PENDING->value => 'warning',
                                            PracticeStatus::INACTIVE->value => 'secondary',
                                            default => 'secondary',
                                        };
                                    @endphp
                                    <span
                                        class="badge bg-label-{{ $statusBadge }} text-uppercase">{{ strtoupper($statusKey ?? 'N/A') }}</span>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group" role="group">
                                        <a href="{{ route('admin.practices.show', $practice->id) }}"
                                            class="btn btn-sm btn-outline-info" title="View">
                                            <i class="ti tabler-eye"></i>
                                        </a>
                                        <a href="{{ route('admin.practices.edit', $practice->id) }}"
                                            class="btn btn-sm btn-outline-primary" title="Edit">
                                            <i class="ti tabler-edit"></i>
                                        </a>
                                        <button wire:click="delete({{ $practice->id }})"
                                            class="btn btn-sm btn-outline-danger" title="Delete">
                                            <i class="ti tabler-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <div class="d-flex flex-column align-items-center gap-2">
                                        <i class="ti tabler-inbox" style="font-size: 2rem; color: #ccc;"></i>
                                        <p class="text-muted mb-0">No practices found</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-body border-top">
                {{ $practices->links('livewire::bootstrap') }}
            </div>
        </div>
    </div>
</div>
