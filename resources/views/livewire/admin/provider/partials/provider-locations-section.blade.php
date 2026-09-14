<div class="card shadow-sm border-0">
    <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <h5 class="mb-0">Provider Practice Locations</h5>
            <small class="text-muted">All practice locations this provider works at — not just the primary site.</small>
        </div>
        @if ($canEdit)
            <button type="button" wire:click="openCreateModal" class="btn btn-sm btn-primary">
                <i class="ti tabler-plus me-1"></i>Add Location
            </button>
        @endif
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Practice</th>
                    <th>Location</th>
                    <th>Address</th>
                    <th>Role</th>
                    <th>Primary</th>
                    <th>Start</th>
                    <th>End</th>
                    @if ($canEdit)
                        <th class="text-end">Actions</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse($links as $link)
                    <tr wire:key="ppl-{{ $link->id }}">
                        <td>
                            @if ($link->practice)
                                <a href="{{ route('admin.practices.show', $link->practice_id) }}" class="fw-semibold">{{ $link->practice->legal_name }}</a>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            <div class="fw-semibold">{{ $link->location->name ?? ('Location #'.$link->location_id) }}</div>
                        </td>
                        <td>
                            @if ($link->location)
                                <small class="text-muted">
                                    {{ $link->location->address1 }}{{ $link->location->address2 ? ', '.$link->location->address2 : '' }}
                                    <br>
                                    {{ $link->location->city }}{{ $link->location->state ? ', '.$link->location->state : '' }}
                                    {{ $link->location->zip_code }}
                                </small>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>{{ $link->role ?: '—' }}</td>
                        <td>
                            <span class="badge bg-label-{{ $link->is_primary ? 'success' : 'secondary' }}">
                                {{ $link->is_primary ? 'Primary' : 'Secondary' }}
                            </span>
                        </td>
                        <td><small>{{ $link->start_date?->format('m/d/Y') ?: '—' }}</small></td>
                        <td><small>{{ $link->end_date?->format('m/d/Y') ?: '—' }}</small></td>
                        @if ($canEdit)
                            <td class="text-end">
                                <button type="button" wire:click="openEditModal({{ $link->id }})" class="btn btn-sm btn-outline-primary" title="Edit">
                                    <i class="ti tabler-edit"></i>
                                </button>
                                <button type="button" wire:click="delete({{ $link->id }})" class="btn btn-sm btn-outline-danger" title="Remove">
                                    <i class="ti tabler-trash"></i>
                                </button>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $canEdit ? 8 : 7 }}" class="text-center py-5 text-muted">
                            No practice locations linked. Add every site this provider works at.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if ($showModal)
    <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,.5);">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ $modalMode === 'create' ? 'Link Location' : 'Edit Location Link' }}</h5>
                    <button type="button" class="btn-close" wire:click="closeModal"></button>
                </div>
                <form wire:submit.prevent="save">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Practice <span class="text-danger">*</span></label>
                            <select wire:model.live="formData.practice_id" class="form-select @error('formData.practice_id') is-invalid @enderror">
                                <option value="">Select practice...</option>
                                @foreach ($practices as $practice)
                                    <option value="{{ $practice->id }}">{{ $practice->legal_name }}</option>
                                @endforeach
                            </select>
                            @error('formData.practice_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Location <span class="text-danger">*</span></label>
                            <select wire:model="formData.location_id" class="form-select @error('formData.location_id') is-invalid @enderror">
                                <option value="">Select location...</option>
                                @foreach ($availableLocations as $location)
                                    <option value="{{ $location['id'] }}">{{ $location['label'] }}</option>
                                @endforeach
                            </select>
                            @error('formData.location_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            @if ($formData['practice_id'] && count($availableLocations) === 0)
                                <small class="text-muted">This practice has no facility locations yet. Add them on the practice record first.</small>
                            @endif
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Role</label>
                            <input type="text" wire:model="formData.role" class="form-control" placeholder="e.g. Attending, Covering">
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Start Date</label>
                                <input type="date" wire:model="formData.start_date" class="form-control">
                                @error('formData.start_date')<div class="text-danger small">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">End Date</label>
                                <input type="date" wire:model="formData.end_date" class="form-control">
                                @error('formData.end_date')<div class="text-danger small">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="form-check form-switch mt-3">
                            <input class="form-check-input" type="checkbox" wire:model="formData.is_primary" id="pplPrimary">
                            <label class="form-check-label" for="pplPrimary">Primary location for this provider</label>
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
