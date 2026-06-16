<div>
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Manage {{ $title }}</h5>
                        <button wire:click="create" class="btn btn-primary">Add New</button>
                    </div>
                    <div class="card-body">
                        @if (session()->has('message'))
                            <div class="alert alert-success alert-dismissible fade-show" role="alert">
                                {{ session('message') }}
                                <button type="button" class="btn-close" data-bs-dismiss="alert"
                                    aria-label="Close"></button>
                            </div>
                        @endif

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <input type="text" class="form-control" placeholder="Search..."
                                    wire:model.live="search">
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th style="cursor:pointer" wire:click="sortBy(\name\)">Name
                                            {{ $sortField == 'name' ? ($sortDirection == 'asc' ? '↑' : '↓') : '' }}</th>
                                        @if ($showSortOrder)
                                            <th style="cursor:pointer" wire:click="sortBy(\sort_order\)">Order
                                                {{ $sortField == 'sort_order' ? ($sortDirection == 'asc' ? '↑' : '↓') : '' }}
                                            </th>
                                        @endif
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($records as $record)
                                        <tr>
                                            <td>{{ $record->name }}</td>
                                            @if ($showSortOrder)
                                                <td>{{ $record->sort_order }}</td>
                                            @endif
                                            <td>
                                                <span
                                                    class="badge {{ $record->is_active ? 'bg-success' : 'bg-danger' }}">
                                                    {{ $record->is_active ? 'Active' : 'Inactive' }}
                                                </span>
                                            </td>
                                            <td>
                                                <button wire:click="edit({{ $record->id }})"
                                                    class="btn btn-sm btn-info text-white">Edit</button>
                                                <button wire:click="delete({{ $record->id }})"
                                                    class="btn btn-sm btn-danger"
                                                    onclick="confirm(\Are
you
sure?\)">Delete</button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="d-flex justify-content-center">
                            {{ $records->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if ($isEdit || $id)
            <div class="modal fade show" style="display: block; background: rgba(0,0,0,0.5);" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">{{ $isEdit ? 'Edit' : 'Add' }} {{ $title }}</h5>
                            <button type="button" class="btn-close" wire:click="create"></button>
                        </div>
                        <div class="modal-body">
                            <form wire:submit.prevent="{{ $isEdit ? 'update' : 'store' }}">
                                <div class="mb-3">
                                    <label class="form-label">Name</label>
                                    <input type="text" class="form-control @error('name') is-invalid @enderror"
                                        wire:model="name">
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                @if ($showSortOrder)
                                    <div class="mb-3">
                                        <label class="form-label">Sort Order</label>
                                        <input type="number"
                                            class="form-control @error('sort_order') is-invalid @enderror"
                                            wire:model="sort_order">
                                        @error('sort_order')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                @endif
                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="isActive"
                                        wire:model="is_active">
                                    <label class="form-check-label" for="isActive">Is Active</label>
                                </div>
                                <div class="modal-footer px-0 pb-0">
                                    <button type="button" class="btn btn-secondary" wire:click="create">Cancel</button>
                                    <button type="submit" class="btn btn-primary">Save Changes</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
