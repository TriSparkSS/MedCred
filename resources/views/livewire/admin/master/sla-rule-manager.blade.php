<div class="container-fluid px-3 px-md-4 py-4">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <div class="row align-items-center g-3">
                <div class="col-lg-3">
                    <h4 class="mb-1 fw-bold text-primary">SLA Rules</h4>
                    <small class="text-muted">Configure SLA timers and automated actions</small>
                </div>
                <div class="col-lg-6">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="ti tabler-search"></i></span>
                        <input type="search" wire:model.live="search" class="form-control border-start-0"
                            placeholder="Search SLA rules...">
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
                        <th>Rule Key</th>
                        <th>Category</th>
                        <th>Business Days</th>
                        <th>Action</th>
                        <th>Template</th>
                        <th>Active</th>
                        <th width="120" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($records as $record)
                        <tr>
                            <td class="fw-semibold">{{ $record->name }}</td>
                            <td><code>{{ $record->rule_key }}</code></td>
                            <td><span
                                    class="badge bg-label-info">{{ $dashboardCategories[$record->dashboard_category] ?? ($record->dashboard_category ?: 'N/A') }}</span>
                            </td>
                            <td>{{ $record->business_days }}</td>
                            <td>{{ $actions[$record->action] ?? $record->action }}</td>
                            <td><small>{{ $record->notificationTemplate->name ?? 'None' }}</small></td>
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
                            <td colspan="8" class="text-center py-5 text-muted">No SLA rules found.</td>
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
                        <h5 class="modal-title">{{ $modalMode === 'create' ? 'Create SLA Rule' : 'Edit SLA Rule' }}
                        </h5>
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
                                    <label class="form-label">Rule Key</label>
                                    <input type="text" wire:model="formData.rule_key"
                                        class="form-control @error('formData.rule_key') is-invalid @enderror">
                                    @error('formData.rule_key')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Dashboard Category</label>
                                    <select wire:model="formData.dashboard_category" class="form-select">
                                        <option value="">Select category...</option>
                                        @foreach ($dashboardCategories as $key => $label)
                                            <option value="{{ $key }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Business Days</label>
                                    <input type="number" wire:model="formData.business_days"
                                        class="form-control @error('formData.business_days') is-invalid @enderror"
                                        min="1">
                                    @error('formData.business_days')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Action</label>
                                    <select wire:model="formData.action"
                                        class="form-select @error('formData.action') is-invalid @enderror">
                                        @foreach ($actions as $key => $label)
                                            <option value="{{ $key }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error('formData.action')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Notification Template</label>
                                    <select wire:model="formData.notification_template_id" class="form-select">
                                        <option value="">None</option>
                                        @foreach ($notificationTemplates as $template)
                                            <option value="{{ $template->id }}">{{ $template->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-12">
                                    <div class="form-check form-switch">
                                        <input type="checkbox" class="form-check-input" id="slaIsActive"
                                            wire:model="formData.is_active">
                                        <label class="form-check-label" for="slaIsActive">Active Rule</label>
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
