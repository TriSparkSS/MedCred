<div class="container-fluid flex-grow-1 px-3 px-md-4 py-3 py-md-4">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-start gap-3">
            <div>
                <h4 class="fw-bold text-primary mb-1"><i class="ti tabler-building-bank me-2"></i>Payer Management</h4>
                <p class="text-muted mb-0">Configure payers, submission channels, and document requirements.</p>
            </div>
            <a href="{{ route('admin.payers.create') }}" class="btn btn-primary">
                <i class="ti tabler-plus me-1"></i>New Payer
            </a>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <p class="text-uppercase text-muted small mb-1">Total Payers</p>
                    <h3 class="fw-bold mb-0">{{ $stats['total'] }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <p class="text-uppercase text-muted small mb-1">Active</p>
                    <h3 class="fw-bold mb-0">{{ $stats['active'] }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <p class="text-uppercase text-muted small mb-1">Inactive</p>
                    <h3 class="fw-bold mb-0">{{ $stats['inactive'] }}</h3>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-8">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="ti tabler-search"></i></span>
                        <input type="search" wire:model.live="search" class="form-control border-start-0"
                            placeholder="Search by payer name, states, or email...">
                    </div>
                </div>
                <div class="col-md-4">
                    <select wire:model.live="filterActive" class="form-select">
                        <option value="">All</option>
                        <option value="active">Active Only</option>
                        <option value="inactive">Inactive Only</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Payer Name</th>
                        <th>States</th>
                        <th>Channel</th>
                        <th>Turnaround</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($payers as $payer)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $payer->name }}</div>
                                <small class="text-muted">{{ $payer->email ?: 'No email' }}</small>
                            </td>
                            <td><small>{{ $payer->states_applicable ?: 'All' }}</small></td>
                            <td><span class="badge bg-label-primary text-uppercase">{{ $payer->submission_channel ?: 'N/A' }}</span></td>
                            <td><small>{{ $payer->turnaround_days ? $payer->turnaround_days . ' days' : 'N/A' }}</small></td>
                            <td>
                                <span class="badge {{ $payer->is_active ? 'bg-success' : 'bg-secondary' }}">
                                    {{ $payer->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('admin.payers.edit', $payer->id) }}" class="btn btn-sm btn-icon btn-outline-primary">
                                    <i class="ti tabler-edit"></i>
                                </a>
                                <button wire:click="delete({{ $payer->id }})" class="btn btn-sm btn-icon btn-outline-danger">
                                    <i class="ti tabler-trash"></i>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">No payers found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white">{{ $payers->links() }}</div>
    </div>
</div>
