<div class="modal-body">
    <div class="row g-3">
        @if ($showCreateModal ?? false)
            @php
                $portalVisible = \App\Enums\TaskType::tryFrom($formData['task_type'] ?? '')?->isProviderPortalVisible() ?? false;
                $hasProvider = ! empty($formData['provider_id']);
            @endphp
            @if ($portalVisible && $hasProvider)
                <div class="col-12">
                    <div class="alert alert-info py-2 mb-0 small">
                        <i class="ti tabler-info-circle me-1"></i>
                        This task will appear in the provider's Action Items once saved.
                    </div>
                </div>
            @endif
        @endif
        <div class="col-12">
            <label class="form-label">Title <span class="text-danger">*</span></label>
            <input type="text" wire:model="formData.title" class="form-control @error('formData.title') is-invalid @enderror">
            @error('formData.title')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-12">
            <label class="form-label">Description</label>
            <textarea wire:model="formData.description" rows="2" class="form-control"></textarea>
        </div>
        <div class="col-md-6">
            <label class="form-label">Assign To (Revantage Staff)</label>
            <select wire:model="formData.assigned_admin_id" class="form-select">
                <option value="">Unassigned</option>
                @foreach ($admins as $admin)
                    <option value="{{ $admin->id }}">{{ $admin->displayLabel() }}</option>
                @endforeach
            </select>
            <small class="text-muted">Internal team member responsible for this task.</small>
        </div>
        <div class="col-md-6">
            <label class="form-label">Due Date</label>
            <input type="date" wire:model="formData.due_date" class="form-control">
        </div>
        <div class="col-md-6">
            <label class="form-label">Follow-up Date</label>
            <input type="date" wire:model="formData.follow_up_date" class="form-control">
        </div>
        <div class="col-md-6">
            <label class="form-label">Related Provider</label>
            <select wire:model.live="formData.provider_id" class="form-select">
                <option value="">None</option>
                @foreach ($providers as $provider)
                    <option value="{{ $provider->id }}">{{ $provider->user->name ?? 'Provider #' . $provider->id }}</option>
                @endforeach
            </select>
            <small class="text-muted">Links this task to a provider; does not assign the portal user.</small>
        </div>
        <div class="col-md-6">
            <label class="form-label">Credentialing Case</label>
            <select wire:model="formData.credentialing_case_id" class="form-select">
                <option value="">None</option>
                @foreach ($cases as $case)
                    <option value="{{ $case->id }}">{{ $case->case_number }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label">Payer</label>
            <select wire:model="formData.payer_id" class="form-select">
                <option value="">None</option>
                @foreach ($payers as $payer)
                    <option value="{{ $payer->id }}">{{ $payer->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label">Priority</label>
            <select wire:model="formData.priority_id" class="form-select">
                <option value="">None</option>
                @foreach ($priorities as $priority)
                    <option value="{{ $priority->id }}">{{ $priority->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label">Task Type</label>
            <select wire:model.live="formData.task_type" class="form-select">
                <option value="manual">Manual</option>
                <option value="follow_up">Follow-up</option>
                <option value="document">Document</option>
                <option value="provider_follow_up">Provider Follow-up</option>
                <option value="expiry">Expiry</option>
                <option value="renewal">Renewal</option>
            </select>
        </div>
    </div>
</div>
