<div class="container-fluid px-3 px-md-4 py-4">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <div class="row align-items-center g-3">
                <div class="col-lg-3">
                    <h4 class="mb-1 fw-bold text-primary">Case Types</h4>
                    <small class="text-muted">Manage system master data</small>
                </div>
                <div class="col-lg-6">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="ti tabler-search"></i></span>
                        <input type="search" wire:model.live="search" class="form-control border-start-0"
                            placeholder="Search Case Types...">
                    </div>
                </div>
                <div class="col-lg-3">
                    <div class="d-flex justify-content-lg-end gap-2">
                        @if ($search)
                            <button wire:click="$set('search', '')" class="btn btn-outline-secondary"><i
                                    class="ti tabler-x"></i></button>
                        @endif
                        <button wire:click="openCreateModal" class="btn btn-primary"><i class="ti tabler-plus me-1"></i>
                            Add New</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white border-bottom">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0 fw-semibold">Case Types List</h5>
                    <small class="text-muted">{{ $records->total() }} Records Found</small>
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th width="60">#</th>
                        <th>Case Type Name</th>
                        <th>Status</th>
                        <th width="120" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($records as $record)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>
                                <div class="fw-semibold">{{ $record->name }}</div>
                            </td>
                            <td>
                                <span class="badge {{ $record->is_active ? 'bg-success' : 'bg-danger' }}">
                                    {{ $record->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="text-end">
                                <button wire:click="openEditModal({{ $record->id }})"
                                    class="btn btn-sm btn-icon btn-outline-primary"><i
                                        class="ti tabler-edit"></i></button>
                                <button wire:click="delete({{ $record->id }})"
                                    class="btn btn-sm btn-icon btn-outline-danger"><i
                                        class="ti tabler-trash"></i></button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center py-5">
                                <i class="ti tabler-folder-off fs-1 text-muted"></i>
                                <div class="mt-2">No records found</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white">
            {{ $records->links() }}
        </div>
    </div>

    @if ($showModal)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,.5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            @if ($modalMode === 'create')
                                Create Case Type
                            @else
                                Edit Case Type
                            @endif
                        </h5>
                        <button type="button" class="btn-close" wire:click="closeModal"></button>
                    </div>
                    <form wire:submit.prevent="save">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Case Type Name</label>
                                <input type="text" wire:model="formData.name"
                                    class="form-control @error('formData.name') is-invalid @enderror"
                                    placeholder="Case Type Name">
                                @error('formData.name')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="mb-3 form-check form-switch">
                                <input type="checkbox" class="form-check-input" id="isActive"
                                    wire:model="formData.is_active">
                                <label class="form-check-label" for="isActive">Active Status</label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" wire:click="closeModal">Cancel</button>
                            <button type="submit" class="btn btn-primary">
                                @if ($modalMode === 'create')
                                    Create
                                @else
                                    Update
                                @endif
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
