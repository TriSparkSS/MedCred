<div class="container-fluid flex-grow-1 px-3 px-md-4 py-3 py-md-4">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-start gap-3">
            <div>
                <h4 class="fw-bold text-primary mb-1"><i class="ti tabler-clipboard-plus me-2"></i>New Credentialing Application</h4>
                <p class="text-muted mb-0">Create a payer enrollment case linked to a provider and practice.</p>
            </div>
            <a href="{{ route('admin.credentials') }}" class="btn btn-outline-secondary"><i class="ti tabler-arrow-left me-1"></i>Back to Tracker</a>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-4">
            <form wire:submit.prevent="save">
                <div class="mb-3 pb-3 border-bottom">
                    <h6 class="text-primary fw-semibold mb-1"><i class="ti tabler-link me-2"></i>Case Assignment</h6>
                </div>
                <div class="row g-4 mb-4">
                    <div class="col-md-4">
                        <label class="form-label fw-medium">Provider <span class="text-danger">*</span></label>
                        <select wire:model="formData.provider_id" class="form-select @error('formData.provider_id') is-invalid @enderror">
                            <option value="">Select provider...</option>
                            @foreach ($providers as $provider)
                                <option value="{{ $provider->id }}">{{ $provider->user->name ?? 'N/A' }} ({{ $provider->npi }})</option>
                            @endforeach
                        </select>
                        @error('formData.provider_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-medium">Practice <span class="text-danger">*</span></label>
                        <select wire:model.live="formData.practice_id" class="form-select @error('formData.practice_id') is-invalid @enderror">
                            <option value="">Select practice...</option>
                            @foreach ($practices as $practice)
                                <option value="{{ $practice->id }}">{{ $practice->legal_name }}</option>
                            @endforeach
                        </select>
                        @error('formData.practice_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-medium">Location</label>
                        <select wire:model="formData.location_id" class="form-select" @disabled(empty($practiceLocations))>
                            <option value="">Select location...</option>
                            @foreach ($practiceLocations as $loc)
                                <option value="{{ $loc['id'] }}">{{ $loc['name'] }}{{ $loc['is_primary'] ? ' (Primary)' : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-medium">Payer <span class="text-danger">*</span></label>
                        <select wire:model="formData.payer_id" class="form-select @error('formData.payer_id') is-invalid @enderror">
                            <option value="">Select payer...</option>
                            @foreach ($payers as $payer)
                                <option value="{{ $payer->id }}">{{ $payer->name }}</option>
                            @endforeach
                        </select>
                        @error('formData.payer_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-medium">Case Type</label>
                        <select wire:model="formData.case_type_id" class="form-select">
                            <option value="">Select type...</option>
                            @foreach ($caseTypes as $type)
                                <option value="{{ $type->id }}">{{ $type->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-medium">State</label>
                        <input type="text" wire:model="formData.state" class="form-control" placeholder="Application state">
                    </div>
                </div>

                <div class="mb-3 pb-3 border-bottom">
                    <h6 class="text-primary fw-semibold mb-1"><i class="ti tabler-settings me-2"></i>Workflow</h6>
                </div>
                <div class="row g-4 mb-4">
                    <div class="col-md-4">
                        <label class="form-label fw-medium">Status <span class="text-danger">*</span></label>
                        <select wire:model.live="formData.status_id" class="form-select @error('formData.status_id') is-invalid @enderror">
                            @foreach ($statuses as $status)
                                <option value="{{ $status->id }}">{{ $status->name }}</option>
                            @endforeach
                        </select>
                        @error('formData.status_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-medium">Delay Owner</label>
                        <select wire:model="formData.delay_owner_id" class="form-select">
                            <option value="">Auto from status</option>
                            @foreach ($delayOwners as $owner)
                                <option value="{{ $owner->id }}">{{ $owner->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-medium">Priority</label>
                        <select wire:model="formData.priority_id" class="form-select">
                            <option value="">Select priority...</option>
                            @foreach ($priorities as $priority)
                                <option value="{{ $priority->id }}">{{ $priority->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-medium">Assigned To</label>
                        <select wire:model="formData.assigned_admin_id" class="form-select">
                            <option value="">Unassigned</option>
                            @foreach ($admins as $admin)
                                <option value="{{ $admin->id }}">{{ $admin->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="mb-3 pb-3 border-bottom">
                    <h6 class="text-primary fw-semibold mb-1"><i class="ti tabler-calendar me-2"></i>Key Dates</h6>
                </div>
                <div class="row g-4 mb-4">
                    <div class="col-md-4">
                        <label class="form-label fw-medium">Intake Date</label>
                        <input type="date" wire:model="formData.intake_date" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-medium">Submission Date</label>
                        <input type="date" wire:model="formData.submission_date" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-medium">Next Follow-up</label>
                        <input type="date" wire:model="formData.next_follow_up_date" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-medium">Expected Completion</label>
                        <input type="date" wire:model="formData.expected_completion_date" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-medium">Effective Date</label>
                        <input type="date" wire:model="formData.effective_date" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-medium">Revalidation Due</label>
                        <input type="date" wire:model="formData.revalidation_due_date" class="form-control">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-medium">Notes</label>
                        <textarea wire:model="formData.notes" rows="3" class="form-control" placeholder="Initial case notes..."></textarea>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                    @if ($duplicateWarning)
                        <div class="alert alert-warning py-2 px-3 me-auto mb-0 small flex-grow-1">
                            {{ $duplicateWarning }}
                            <button type="button" wire:click="saveAnyway" class="btn btn-sm btn-warning ms-2">Create Anyway</button>
                        </div>
                    @endif
                    <a href="{{ route('admin.credentials') }}" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary"><i class="ti tabler-check me-1"></i>Create Application</button>
                </div>
            </form>
        </div>
    </div>
</div>
