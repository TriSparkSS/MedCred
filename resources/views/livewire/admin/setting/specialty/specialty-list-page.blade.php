<div class="container-fluid px-3 px-md-4 py-4">

    <!-- Search + Action Card -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">

            <div class="row align-items-center g-3">

                <!-- Title -->
                <div class="col-lg-3">
                    <h4 class="mb-1 fw-bold text-primary">
                        Specialties
                    </h4>
                    <small class="text-muted">
                        Manage medical specialties
                    </small>
                </div>

                <!-- Search -->
                <div class="col-lg-6">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0">
                            <i class="ti tabler-search"></i>
                        </span>

                        <input type="search" wire:model.live="search" class="form-control border-start-0"
                            placeholder="Search specialties...">
                    </div>
                </div>

                <!-- Actions -->
                <div class="col-lg-3">
                    <div class="d-flex justify-content-lg-end gap-2">

                        @if ($search)
                            <button wire:click="$set('search', '')" class="btn btn-outline-secondary">
                                <i class="ti tabler-x"></i>
                            </button>
                        @endif

                        <button wire:click="openCreateModal" class="btn btn-primary">
                            <i class="ti tabler-plus me-1"></i>
                            Add Specialty
                        </button>

                    </div>
                </div>

            </div>

        </div>
    </div>


    <!-- Listing Card -->
    <div class="card shadow-sm border-0">

        <div class="card-header bg-white border-bottom">
            <div class="d-flex justify-content-between align-items-center">

                <div>
                    <h5 class="mb-0 fw-semibold">
                        Specialty List
                    </h5>

                    <small class="text-muted">
                        {{ $specialties->total() }} Records Found
                    </small>
                </div>

            </div>
        </div>

        <div class="table-responsive">

            <table class="table table-hover align-middle mb-0">

                <thead class="table-light">
                    <tr>
                        <th width="60">#</th>
                        <th>Specialty Name</th>
                        <th>Description</th>
                        <th>Created Date</th>
                        <th width="120" class="text-end">Actions</th>
                    </tr>
                </thead>

                <tbody>

                    @forelse($specialties as $specialty)
                        <tr>
                            <td>
                                {{ $loop->iteration }}
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div>
                                        <div class="fw-semibold">
                                            {{ $specialty->name }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="text-muted">
                                    {{ Str::limit($specialty->description, 60) ?: 'N/A' }}
                                </span>
                            </td>

                            <td>
                                {{ $specialty->created_at->format('d M Y') }}
                            </td>

                            <td class="text-end">

                                <button wire:click="openEditModal({{ $specialty->id }})"
                                    class="btn btn-sm btn-icon btn-outline-primary">
                                    <i class="ti tabler-edit"></i>
                                </button>

                                <button wire:click="delete({{ $specialty->id }})"
                                    class="btn btn-sm btn-icon btn-outline-danger">
                                    <i class="ti tabler-trash"></i>
                                </button>

                            </td>

                        </tr>

                    @empty

                        <tr>
                            <td colspan="5" class="text-center py-5">

                                <i class="ti tabler-folder-off fs-1 text-muted"></i>

                                <div class="mt-2">
                                    No specialties found
                                </div>

                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white">
            {{ $specialties->links() }}
        </div>
    </div>

    @if ($showModal)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,.5);">

            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">

                    <div class="modal-header">
                        <h5 class="modal-title">

                            @if ($modalMode === 'create')
                                Create Specialty
                            @else
                                Edit Specialty
                            @endif

                        </h5>

                        <button type="button" class="btn-close" wire:click="closeModal">
                        </button>
                    </div>

                    <form wire:submit="save">

                        <div class="modal-body">

                            <div class="mb-3">
                                <label class="form-label">
                                    Specialty Name
                                </label>

                                <input type="text" wire:model="formData.name" class="form-control" placeholder="Specialty Name">

                                @error('formData.name')
                                    <span class="text-danger">
                                        {{ $message }}
                                    </span>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label">
                                    Description
                                </label>

                                <textarea wire:model="formData.description" class="form-control" rows="4" placeholder="Description (Optional)"></textarea>

                                @error('formData.description')
                                    <span class="text-danger">
                                        {{ $message }}
                                    </span>
                                @enderror
                            </div>

                        </div>

                        <div class="modal-footer">

                            <button type="button" class="btn btn-secondary" wire:click="closeModal">
                                Cancel
                            </button>

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
