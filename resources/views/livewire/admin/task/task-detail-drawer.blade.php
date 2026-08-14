<div>
@if ($show && $task)
    <div class="offcanvas offcanvas-end show" tabindex="-1" style="visibility: visible; width: 420px;">
        <div class="offcanvas-header border-bottom">
            <div>
                <h5 class="offcanvas-title mb-1">{{ $task->title }}</h5>
                <span class="badge bg-label-secondary">{{ $taskTypeLabel }}</span>
                @if ($task->status)
                    <span class="badge bg-label-primary ms-1">{{ $task->status->label() }}</span>
                @endif
            </div>
            <button type="button" class="btn-close" wire:click="close"></button>
        </div>
        <div class="offcanvas-body">
            @if ($task->description)
                <p class="text-muted small">{{ $task->description }}</p>
            @endif

            <dl class="row small mb-3">
                <dt class="col-5">Assigned To (Staff)</dt>
                <dd class="col-7">{{ $task->assignedAdmin?->displayLabel() ?? 'Unassigned' }}</dd>
                <dt class="col-5">Due Date</dt>
                <dd class="col-7">{{ $task->due_date?->format('m/d/Y') ?: '—' }}</dd>
                <dt class="col-5">Follow-up</dt>
                <dd class="col-7">{{ $task->follow_up_date?->format('m/d/Y') ?: '—' }}</dd>
                @if ($task->credentialingCase)
                    <dt class="col-5">Case</dt>
                    <dd class="col-7">{{ $task->credentialingCase->case_number }}</dd>
                @endif
            </dl>

            <div class="d-flex flex-wrap gap-1 mb-4">
                @if (!$task->isCompleted())
                    @can('update', $task)
                        <button wire:click="complete" class="btn btn-sm btn-success">Complete</button>
                    @endcan
                    @can('escalate', $task)
                        <button wire:click="escalate" class="btn btn-sm btn-danger">Escalate</button>
                    @endcan
                @else
                    @can('reopen', $task)
                        <button wire:click="reopen" class="btn btn-sm btn-outline-secondary">Reopen</button>
                    @endcan
                @endif
            </div>

            @can('assign', $task)
                <div class="border rounded p-3 mb-4">
                    <label class="form-label small fw-semibold">Reassign</label>
                    <select wire:model="reassignAdminId" class="form-select form-select-sm mb-2">
                        <option value="">Unassigned</option>
                        @foreach ($admins as $admin)
                            <option value="{{ $admin->id }}">{{ $admin->displayLabel() }}</option>
                        @endforeach
                    </select>
                    <input type="text" wire:model="reassignReason" class="form-control form-control-sm mb-2" placeholder="Reason (required)">
                    <button wire:click="reassign" class="btn btn-sm btn-primary">Reassign</button>
                </div>
            @endcan

            <h6 class="fw-semibold">Notes</h6>
            <div class="mb-3" style="max-height: 160px; overflow-y: auto;">
                @forelse($task->notes as $note)
                    <div class="border rounded p-2 mb-2 small">
                        <div class="text-muted">{{ $note->admin->name ?? 'System' }} · {{ $note->created_at->format('m/d/Y g:i A') }}</div>
                        <div>{{ $note->body }}</div>
                    </div>
                @empty
                    <p class="text-muted small">No notes yet.</p>
                @endforelse
            </div>
            @can('update', $task)
                <div class="mb-4">
                    <textarea wire:model="noteBody" rows="2" class="form-control form-control-sm mb-2" placeholder="Add a note..."></textarea>
                    <button wire:click="addNote" class="btn btn-sm btn-outline-primary">Add Note</button>
                </div>
            @endcan

            <h6 class="fw-semibold">Attachments</h6>
            @forelse($task->attachments as $attachment)
                <div class="small mb-1"><i class="ti tabler-paperclip me-1"></i>{{ $attachment->original_name }}</div>
            @empty
                <p class="text-muted small">No attachments.</p>
            @endforelse
            @can('update', $task)
                <input type="file" wire:model="attachment" class="form-control form-control-sm mt-2">
                @if ($attachment)
                    <button wire:click="uploadAttachment" class="btn btn-sm btn-outline-primary mt-2">Upload</button>
                @endif
            @endcan

            <h6 class="fw-semibold mt-4">Activity</h6>
            <div style="max-height: 200px; overflow-y: auto;">
                @foreach ($task->activities as $activity)
                    <div class="small border-start border-2 ps-2 mb-2">
                        <div class="text-muted">{{ $activity->created_at->format('m/d/Y g:i A') }}</div>
                        <div>{{ $activity->summary }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    <div class="offcanvas-backdrop fade show" wire:click="close"></div>
@endif
</div>
