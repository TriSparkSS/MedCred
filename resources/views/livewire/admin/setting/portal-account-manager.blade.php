<div>
    <div class="container-fluid px-3 px-md-4 py-4">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <div class="row align-items-center g-3">
                    <div class="col-lg-4">
                        <h4 class="mb-1 fw-bold text-primary">Portal Accounts</h4>
                        <small class="text-muted">Practice and provider logins for <code>{{ url('/portal/login') }}</code></small>
                    </div>
                    <div class="col-lg-5">
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0"><i class="ti tabler-search"></i></span>
                            <input type="search" wire:model.live="search" class="form-control border-start-0"
                                placeholder="Search name, email, practice, client code, or NPI...">
                        </div>
                    </div>
                    <div class="col-lg-3">
                        <select wire:model.live="filterType" class="form-select">
                            <option value="">All portal types</option>
                            <option value="practice">Practice</option>
                            <option value="provider">Provider</option>
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
                            <th>Name</th>
                            <th>Email</th>
                            <th>Type</th>
                            <th>Linked record</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($records as $record)
                            <tr wire:key="portal-account-{{ $record->id }}">
                                <td class="fw-semibold">{{ $record->name }}</td>
                                <td>{{ $record->email }}</td>
                                <td>
                                    @if ($record->practice)
                                        <span class="badge bg-label-info">Practice</span>
                                    @else
                                        <span class="badge bg-label-primary">Provider</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($record->practice)
                                        <a href="{{ route('admin.practices.show', $record->practice) }}">
                                            {{ $record->practice->legal_name }}
                                        </a>
                                        @if ($record->practice->client_code)
                                            <small class="text-muted">({{ $record->practice->client_code }})</small>
                                        @endif
                                    @elseif ($record->providerDetails)
                                        <a href="{{ route('admin.providers.show', $record->providerDetails) }}">
                                            {{ $record->name }}
                                        </a>
                                        @if ($record->providerDetails->npi)
                                            <small class="text-muted">NPI {{ $record->providerDetails->npi }}</small>
                                        @endif
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <button type="button" wire:click="openEditModal({{ $record->id }})" class="btn btn-sm btn-outline-primary">
                                        <i class="ti tabler-edit me-1"></i>Edit
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">No portal accounts found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white">{{ $records->links('livewire::bootstrap') }}</div>
        </div>
    </div>

    @if ($showModal)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,.5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Portal Account</h5>
                        <button type="button" class="btn-close" wire:click="closeModal"></button>
                    </div>
                    <form wire:submit.prevent="save">
                        <div class="modal-body">
                            <p class="text-muted small mb-3">
                                {{ $accountType === 'practice' ? 'Practice' : 'Provider' }} login
                                @if ($linkedLabel)
                                    for <span class="fw-semibold">{{ $linkedLabel }}</span>
                                @endif
                            </p>
                            <div class="mb-3">
                                <label class="form-label">Name <span class="text-danger">*</span></label>
                                <input type="text" wire:model="userName" class="form-control @error('userName') is-invalid @enderror">
                                @error('userName')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Login email <span class="text-danger">*</span></label>
                                <input type="email" wire:model="userEmail" class="form-control @error('userEmail') is-invalid @enderror">
                                @error('userEmail')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Phone</label>
                                <input type="text" wire:model="userPhone" class="form-control @error('userPhone') is-invalid @enderror">
                                @error('userPhone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-0">
                                <label class="form-label">New password</label>
                                <input type="password" wire:model="userPassword"
                                    class="form-control @error('userPassword') is-invalid @enderror"
                                    autocomplete="new-password" placeholder="Leave blank to keep current">
                                @error('userPassword')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <div class="form-text">Minimum 6 characters. Used at {{ url('/portal/login') }}.</div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" wire:click="closeModal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
