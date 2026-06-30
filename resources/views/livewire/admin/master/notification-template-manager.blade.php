<div class="container-fluid flex-grow-1 px-3 px-md-4 py-3 py-md-4">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-start gap-3">
            <div>
                <h4 class="fw-bold text-primary mb-1"><i class="ti tabler-template me-2"></i>Email Templates</h4>
                <p class="text-muted mb-0">Manage notification templates for document requests, reminders, and escalations.</p>
            </div>
            <button wire:click="openCreateModal" class="btn btn-primary">
                <i class="ti tabler-plus me-1"></i>New Template
            </button>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <input type="search" wire:model.live.debounce.300ms="search" class="form-control"
                placeholder="Search templates...">
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Key</th>
                        <th>Category</th>
                        <th>Subject</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($records as $record)
                        <tr wire:key="tpl-{{ $record->id }}">
                            <td class="fw-semibold">{{ $record->name }}</td>
                            <td><code>{{ $record->template_key }}</code></td>
                            <td><span class="badge bg-label-secondary">{{ $record->category }}</span></td>
                            <td class="small">{{ Str::limit($record->subject, 40) }}</td>
                            <td>
                                <span class="badge bg-label-{{ $record->is_active ? 'success' : 'secondary' }}">
                                    {{ $record->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="text-end">
                                <button wire:click="openEditModal({{ $record->id }})" class="btn btn-sm btn-outline-primary">Edit</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">No templates. Run <code>php artisan db:seed --class=Phase2Seeder</code> for defaults.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white">{{ $records->links() }}</div>
    </div>

    @if ($showModal)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,.5);">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ $modalMode === 'create' ? 'Create Template' : 'Edit Template' }}</h5>
                        <button type="button" class="btn-close" wire:click="closeModal"></button>
                    </div>
                    <form wire:submit.prevent="save">
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Name</label>
                                    <input type="text" wire:model="formData.name" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Template Key</label>
                                    <input type="text" wire:model="formData.template_key" class="form-control" @if($modalMode === 'edit') readonly @endif>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Category</label>
                                    <select wire:model="formData.category" class="form-select">
                                        <option value="general">General</option>
                                        <option value="request">Request</option>
                                        <option value="reminder">Reminder</option>
                                        <option value="escalation">Escalation</option>
                                        <option value="payer">Payer</option>
                                        <option value="revalidation">Revalidation</option>
                                    </select>
                                </div>
                                <div class="col-md-6 d-flex align-items-end">
                                    <div class="form-check form-switch">
                                        <input type="checkbox" class="form-check-input" wire:model="formData.is_active" id="tplActive">
                                        <label class="form-check-label" for="tplActive">Active</label>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Subject</label>
                                    <input type="text" wire:model="formData.subject" class="form-control">
                                    <small class="text-muted">Variables: @{{case_number}}, @{{provider_name}}, @{{payer_name}}, @{{practice_name}}, @{{state}}, @{{next_follow_up_date}}</small>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Body</label>
                                    <textarea wire:model="formData.body" rows="10" class="form-control"></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" wire:click="closeModal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
