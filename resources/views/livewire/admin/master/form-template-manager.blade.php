<div class="container-fluid px-3 px-md-4 py-4">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <div class="row align-items-center g-3">
                <div class="col-lg-3">
                    <h4 class="mb-1 fw-bold text-primary">Form Templates</h4>
                    <small class="text-muted">Manage credentialing packet form templates</small>
                </div>
                <div class="col-lg-6">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="ti tabler-search"></i></span>
                        <input type="search" wire:model.live="search" class="form-control border-start-0"
                            placeholder="Search form templates...">
                    </div>
                </div>
                <div class="col-lg-3 text-lg-end">
                    <button wire:click="openCreateModal" class="btn btn-primary"><i class="ti tabler-plus me-1"></i>Add
                        New</button>
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
                        <th>Template Key</th>
                        <th>Version</th>
                        <th>Description</th>
                        <th>Active</th>
                        <th width="120" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($records as $record)
                        <tr>
                            <td class="fw-semibold">{{ $record->name }}</td>
                            <td><code>{{ $record->template_key }}</code></td>
                            <td>{{ $record->version }}</td>
                            <td><small class="text-muted">{{ Str::limit($record->description, 60) ?: '—' }}</small></td>
                            <td>
                                <span
                                    class="badge {{ $record->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $record->is_active ? 'Active' : 'Inactive' }}</span>
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
                            <td colspan="6" class="text-center py-5 text-muted">No form templates found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white">{{ $records->links('livewire::bootstrap') }}</div>
    </div>

    @if ($showModal)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,.5);">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            {{ $modalMode === 'create' ? 'Create Form Template' : 'Edit Form Template' }}</h5>
                        <button type="button" class="btn-close" wire:click="closeModal"></button>
                    </div>
                    <form wire:submit.prevent="save">
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Name</label>
                                    <input type="text" wire:model="formData.name"
                                        class="form-control @error('formData.name') is-invalid @enderror">
                                    @error('formData.name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Template Key</label>
                                    <input type="text" wire:model="formData.template_key"
                                        class="form-control @error('formData.template_key') is-invalid @enderror">
                                    @error('formData.template_key')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Version</label>
                                    <input type="text" wire:model="formData.version"
                                        class="form-control @error('formData.version') is-invalid @enderror">
                                    @error('formData.version')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Description</label>
                                    <textarea wire:model="formData.description" class="form-control @error('formData.description') is-invalid @enderror"
                                        rows="3"></textarea>
                                    @error('formData.description')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-12">
                                    <div class="form-check form-switch">
                                        <input type="checkbox" class="form-check-input" id="formTemplateActive"
                                            wire:model="formData.is_active">
                                        <label class="form-check-label" for="formTemplateActive">Active Template</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" wire:click="closeModal">Cancel</button>
                            <button type="submit"
                                class="btn btn-primary">{{ $modalMode === 'create' ? 'Create' : 'Update' }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
