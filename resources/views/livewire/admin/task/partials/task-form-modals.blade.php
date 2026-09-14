@if ($showCreateModal)
    <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,.5);">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">New Task</h5>
                    <button type="button" class="btn-close" wire:click="$set('showCreateModal', false)"></button>
                </div>
                <form wire:submit.prevent="saveTask">
                    @include('livewire.admin.task.partials.task-form-fields')
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" wire:click="$set('showCreateModal', false)">Cancel</button>
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="saveTask">
                            <span wire:loading.remove wire:target="saveTask">Create Task</span>
                            <span wire:loading wire:target="saveTask">Creating...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif

@if ($showEditModal)
    <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,.5);">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Task</h5>
                    <button type="button" class="btn-close" wire:click="$set('showEditModal', false)"></button>
                </div>
                <form wire:submit.prevent="updateTask">
                    @include('livewire.admin.task.partials.task-form-fields')
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" wire:click="$set('showEditModal', false)">Cancel</button>
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="updateTask">
                            <span wire:loading.remove wire:target="updateTask">Save Changes</span>
                            <span wire:loading wire:target="updateTask">Saving...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif

@if ($showBulkFollowUpModal)
    <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,.5);">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add follow-ups</h5>
                    <button type="button" class="btn-close" wire:click="$set('showBulkFollowUpModal', false)"></button>
                </div>
                <form wire:submit.prevent="saveBulkFollowUps">
                    <div class="modal-body">
                        <label class="form-label small fw-medium">Cases</label>
                        <input type="search" wire:model.live.debounce.300ms="followUpCaseSearch"
                            class="form-control form-control-sm mb-2"
                            placeholder="Search case #, payer, or provider…">
                        @error('followUpCaseIds')<div class="text-danger small mb-2">{{ $message }}</div>@enderror

                        @if ($selectedFollowUpCases->isNotEmpty())
                            <div class="d-flex flex-wrap gap-1 mb-2">
                                @foreach ($selectedFollowUpCases as $selectedCase)
                                    <span class="badge bg-label-primary d-inline-flex align-items-center gap-1">
                                        {{ $selectedCase->case_number }}
                                        <button type="button" class="btn-close btn-close-sm"
                                            style="font-size: .55rem;"
                                            wire:click="removeFollowUpCase({{ $selectedCase->id }})"
                                            aria-label="Remove"></button>
                                    </span>
                                @endforeach
                            </div>
                        @endif

                        <div class="border rounded mb-3" style="max-height: 180px; overflow-y: auto;">
                            @forelse ($followUpCaseResults as $result)
                                <button type="button"
                                    class="btn btn-link text-start w-100 rounded-0 border-bottom py-2 px-3"
                                    wire:click="addFollowUpCase({{ $result->id }})">
                                    <span class="fw-medium">{{ $result->case_number }}</span>
                                    <small class="text-muted d-block">{{ $result->payer->name ?? '' }}</small>
                                </button>
                            @empty
                                <p class="text-muted small mb-0 p-3">
                                    {{ $followUpCaseSearch === '' ? 'Type to search open cases, or browse recent results.' : 'No matching open cases.' }}
                                </p>
                            @endforelse
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-medium">Title</label>
                            <input type="text" wire:model="followUpForm.title" class="form-control">
                            @error('followUpForm.title')<div class="text-danger small">{{ $message }}</div>@enderror
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-md-4">
                                <label class="form-label small fw-medium">First due date</label>
                                <input type="date" wire:model="followUpForm.due_date" class="form-control">
                                @error('followUpForm.due_date')<div class="text-danger small">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-medium">Assign to</label>
                                <select wire:model="followUpForm.assigned_admin_id" class="form-select">
                                    <option value="">Unassigned</option>
                                    @foreach ($admins as $admin)
                                        <option value="{{ $admin->id }}">{{ $admin->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-medium">Type</label>
                                <select wire:model="followUpForm.task_type" class="form-select">
                                    <option value="follow_up">Follow-up</option>
                                    <option value="payer_follow_up">Payer Follow-up</option>
                                    <option value="provider_follow_up">Provider Follow-up</option>
                                    <option value="manual">Manual</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-check mb-2">
                            <input type="checkbox" class="form-check-input" id="followUpRepeat"
                                wire:model.live="followUpForm.repeat">
                            <label class="form-check-label" for="followUpRepeat">Repeat as a series</label>
                        </div>

                        @if ($followUpForm['repeat'] ?? false)
                            <div class="row g-2 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-medium">Every (days)</label>
                                    <input type="number" min="1" max="90" wire:model.live="followUpForm.interval_days"
                                        class="form-control">
                                    @error('followUpForm.interval_days')<div class="text-danger small">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-medium">Times</label>
                                    <input type="number" min="2" max="12" wire:model.live="followUpForm.occurrences"
                                        class="form-control">
                                    @error('followUpForm.occurrences')<div class="text-danger small">{{ $message }}</div>@enderror
                                </div>
                            </div>
                        @endif

                        <div class="form-check mb-2">
                            <input type="checkbox" class="form-check-input" id="syncCaseFollowUp"
                                wire:model="followUpForm.sync_case_follow_up">
                            <label class="form-check-label" for="syncCaseFollowUp">
                                Set each case’s next follow-up date to the first due date
                            </label>
                        </div>

                        <p class="text-muted small mb-0">
                            Will create <strong>{{ $followUpPreviewCount }}</strong> task{{ $followUpPreviewCount === 1 ? '' : 's' }}.
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary"
                            wire:click="$set('showBulkFollowUpModal', false)">Cancel</button>
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled"
                            wire:target="saveBulkFollowUps">
                            <span wire:loading.remove wire:target="saveBulkFollowUps">Create follow-ups</span>
                            <span wire:loading wire:target="saveBulkFollowUps">Creating...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif
