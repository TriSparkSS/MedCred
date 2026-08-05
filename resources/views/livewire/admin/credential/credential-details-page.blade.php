<div class="container-fluid px-3 px-md-4 py-4">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-start gap-3">
            <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <h4 class="fw-bold text-primary mb-0">{{ $case->case_number }}</h4>
                    @if ($case->is_escalated)
                        <span class="badge bg-danger">Escalated</span>
                    @endif
                    @if ($case->status)
                        <span class="badge bg-label-secondary">{{ $case->status->name }}</span>
                    @endif
                </div>
                <p class="text-muted mb-0">
                    {{ $case->provider?->user?->name ?? 'N/A' }}
                    · {{ $case->payer?->name ?? 'N/A' }}
                    @if ($case->practice)
                        · {{ $case->practice->legal_name }}
                    @endif
                </p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button type="button"
                    wire:click="toggleEscalation"
                    class="btn {{ $case->is_escalated ? 'btn-danger' : 'btn-outline-danger' }}">
                    <i class="ti tabler-flag me-1"></i>{{ $case->is_escalated ? 'De-escalate' : 'Escalate' }}
                </button>
                <a href="{{ route('admin.credentials') }}" class="btn btn-outline-secondary">
                    <i class="ti tabler-arrow-left me-1"></i>Back to Tracker
                </a>
            </div>
        </div>
    </div>

    <ul class="nav nav-pills flex-wrap gap-1 mb-4">
        @foreach (['details' => 'Details', 'documents' => 'Documents', 'communication' => 'Communication', 'tasks' => 'Tasks', 'billing' => 'Billing', 'timeline' => 'Timeline'] as $key => $label)
            <li class="nav-item">
                <button type="button"
                    class="nav-link {{ $activeTab === $key ? 'active' : '' }}"
                    wire:click="setTab('{{ $key }}')">{{ $label }}</button>
            </li>
        @endforeach
    </ul>

    <div class="card shadow-sm border-0">
        <div class="card-body">
            @if ($activeTab === 'details')
                <div class="row g-4">
                    <div class="col-lg-6">
                        <h6 class="fw-semibold mb-3">Status</h6>
                        <select wire:model="statusId" class="form-select mb-2">
                            @foreach ($statuses as $status)
                                <option value="{{ $status->id }}">{{ $status->name }}</option>
                            @endforeach
                        </select>
                        <button type="button" wire:click="saveStatus" class="btn btn-sm btn-primary">Update Status</button>

                        <h6 class="fw-semibold mb-3 mt-4">Case Details</h6>
                        <div class="row g-2 small">
                            <div class="col-6"><span class="text-muted">Practice:</span> {{ $case->practice?->legal_name ?? 'N/A' }}</div>
                            <div class="col-6"><span class="text-muted">State:</span> {{ $case->state ?: 'N/A' }}</div>
                            <div class="col-6"><span class="text-muted">Intake:</span> {{ $case->intake_date?->format('m/d/Y') ?: 'N/A' }}</div>
                            <div class="col-6"><span class="text-muted">Submitted:</span> {{ $case->submission_date?->format('m/d/Y') ?: 'N/A' }}</div>
                            <div class="col-6"><span class="text-muted">Effective:</span> {{ $case->effective_date?->format('m/d/Y') ?: 'N/A' }}</div>
                            <div class="col-6"><span class="text-muted">Aging:</span> {{ $case->aging_days }} days</div>
                            <div class="col-6"><span class="text-muted">Assignee:</span> {{ $case->assignedAdmin?->name ?? 'Unassigned' }}</div>
                            <div class="col-6"><span class="text-muted">Priority:</span> {{ $case->priority?->name ?? 'N/A' }}</div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <h6 class="fw-semibold mb-3">Delay Ownership</h6>
                        <p class="small text-muted mb-2">Current: <strong>{{ $case->delayOwner?->name ?? 'Unassigned' }}</strong></p>
                        @if ($case->slaTimers->isNotEmpty())
                            <div class="small text-muted mb-2">
                                @foreach ($case->slaTimers as $timer)
                                    <div>{{ str_replace('_', ' ', $timer->rule_key) }} — due {{ $timer->due_at->format('m/d/Y') }}</div>
                                @endforeach
                            </div>
                        @endif
                        <select wire:model="delayOwnerId" class="form-select form-select-sm mb-2">
                            <option value="">Select delay owner...</option>
                            @foreach ($delayOwners as $owner)
                                <option value="{{ $owner->id }}">{{ $owner->name }}</option>
                            @endforeach
                        </select>
                        <input type="text" wire:model="overrideReason" class="form-control form-control-sm mb-2" placeholder="Override reason (required)">
                        @error('overrideReason')<div class="text-danger small">{{ $message }}</div>@enderror
                        <button type="button" wire:click="saveDelayOverride" class="btn btn-sm btn-outline-secondary">Override Delay Owner</button>

                        <h6 class="fw-semibold mb-3 mt-4">Add Note</h6>
                        <textarea wire:model="newNote" rows="3" class="form-control mb-2" placeholder="Log a call, follow-up, or note..."></textarea>
                        @error('newNote')<div class="text-danger small">{{ $message }}</div>@enderror
                        <button type="button" wire:click="addNote" class="btn btn-sm btn-outline-primary">Add Note</button>
                    </div>
                </div>

            @elseif ($activeTab === 'documents')
                @php $checklist = $case->checklist_completion; @endphp
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-semibold mb-0">Document Checklist</h6>
                    <a href="{{ route('admin.credentials.packet', $case) }}"
                        class="btn btn-sm btn-outline-primary" target="_blank">
                        <i class="ti tabler-package me-1"></i>Download Packet
                    </a>
                </div>
                @if ($checklist['total'] > 0)
                    <div class="mb-3" style="max-width: 420px;">
                        <div class="d-flex justify-content-between small mb-1">
                            <span>{{ $checklist['received'] }} of {{ $checklist['total'] }} received</span>
                            <span class="fw-semibold">{{ $checklist['percent'] }}%</span>
                        </div>
                        <div class="progress" style="height: 6px;">
                            <div class="progress-bar bg-success" style="width: {{ $checklist['percent'] }}%"></div>
                        </div>
                    </div>
                    <div class="row g-2">
                        @foreach ($case->documentItems as $item)
                            <div class="col-md-6">
                                <div class="d-flex align-items-center gap-2 small p-2 border rounded">
                                    <i class="ti tabler-{{ $item->is_received ? 'circle-check text-success' : 'circle-dashed text-muted' }}"></i>
                                    <span class="{{ $item->is_received ? '' : 'text-muted' }}">
                                        {{ $item->documentType?->name ?? 'Document' }}
                                        @if ($item->is_required)<span class="text-danger">*</span>@endif
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-muted small mb-0">No checklist items. Configure payer document requirements or upload documents linked to this case.</p>
                @endif

            @elseif ($activeTab === 'communication')
                <div class="row g-4">
                    <div class="col-lg-5">
                        <h6 class="fw-semibold mb-3">Send Email</h6>
                        <select wire:model="emailTemplateId" class="form-select mb-2">
                            <option value="">Select template...</option>
                            @foreach ($emailTemplates as $template)
                                <option value="{{ $template->id }}">{{ $template->name }}</option>
                            @endforeach
                        </select>
                        @error('emailTemplateId')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
                        <button type="button" wire:click="sendDocumentRequest" class="btn btn-sm btn-primary mb-3">
                            <i class="ti tabler-mail me-1"></i>Send to Provider
                        </button>
                        <div>
                            <a href="{{ route('admin.email.dashboard') }}" class="btn btn-sm btn-outline-secondary">
                                <i class="ti tabler-mail me-1"></i>Open Email Center
                            </a>
                        </div>
                    </div>
                    <div class="col-lg-7">
                        <h6 class="fw-semibold mb-3">Linked Messages</h6>
                        @forelse($case->emailCaseLinks as $link)
                            <div class="small border-bottom py-2">
                                <strong class="text-break">{{ $link->message_id }}</strong>
                                <div class="text-muted">Linked {{ $link->created_at->format('m/d/Y g:i A') }}</div>
                            </div>
                        @empty
                            <p class="text-muted small mb-0">No Message-ID links yet. Link emails from Email Center.</p>
                        @endforelse
                    </div>
                </div>

            @elseif ($activeTab === 'tasks')
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-semibold mb-0">Tasks & Follow-ups</h6>
                    <a href="{{ route('admin.tasks.kanban', ['case_id' => $case->id]) }}"
                        class="btn btn-sm btn-outline-primary">
                        <i class="ti tabler-layout-kanban me-1"></i>View in Kanban
                    </a>
                </div>
                <div class="row g-4">
                    <div class="col-lg-7">
                        @forelse($case->tasks as $task)
                            <div class="d-flex justify-content-between align-items-start gap-2 mb-2 p-2 border rounded {{ $task->isCompleted() ? 'bg-light' : '' }}">
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <span class="badge bg-label-secondary">{{ $taskTypeLabel($task->task_type) }}</span>
                                        @if($task->isCompleted())
                                            <span class="badge bg-label-success">Done</span>
                                        @endif
                                    </div>
                                    <div class="small fw-semibold {{ $task->isCompleted() ? 'text-muted text-decoration-line-through' : '' }}">
                                        {{ $task->title }}
                                    </div>
                                    <small class="text-muted">
                                        {{ $task->assignedAdmin?->name ?? 'Unassigned' }}
                                        · {{ $task->due_date?->format('m/d/Y') ?: 'No due date' }}
                                    </small>
                                </div>
                                @if(! $task->isCompleted())
                                    <button type="button" wire:click="completeTask({{ $task->id }})"
                                        class="btn btn-sm btn-outline-success" title="Mark complete">
                                        <i class="ti tabler-check"></i>
                                    </button>
                                @endif
                            </div>
                        @empty
                            <p class="text-muted small mb-0">No tasks linked to this case.</p>
                        @endforelse
                    </div>
                    <div class="col-lg-5">
                        <div class="border rounded p-3">
                            <label class="form-label small fw-medium mb-1">Create Follow-up Task</label>
                            <input type="text" wire:model="newTaskTitle" class="form-control form-control-sm mb-2" placeholder="Task title...">
                            @error('newTaskTitle')<div class="text-danger small">{{ $message }}</div>@enderror
                            <div class="row g-2 mb-2">
                                <div class="col-6">
                                    <input type="date" wire:model="newTaskDueDate" class="form-control form-control-sm">
                                </div>
                                <div class="col-6">
                                    <select wire:model="newTaskAssigneeId" class="form-select form-select-sm">
                                        <option value="">Unassigned</option>
                                        @foreach ($admins as $admin)
                                            <option value="{{ $admin->id }}">{{ $admin->displayLabel() }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <button type="button" wire:click="createFollowUpTask" class="btn btn-sm btn-primary">
                                <i class="ti tabler-plus me-1"></i>Add Task
                            </button>
                        </div>
                    </div>
                </div>

            @elseif ($activeTab === 'billing')
                @php $missingBilling = $billingService->missingFields($case); @endphp
                <h6 class="fw-semibold mb-3">Billing Readiness</h6>
                @if ($case->ready_to_bill)
                    <span class="badge bg-success mb-3">Ready to Bill</span>
                @elseif (count($missingBilling))
                    <div class="alert alert-warning py-2 small">Missing: {{ implode(', ', $missingBilling) }}</div>
                @endif
                <div class="row g-3" style="max-width: 720px;">
                    <div class="col-md-6">
                        <label class="form-label">Approval Date</label>
                        <input type="date" wire:model="billingForm.approval_date" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Effective Date</label>
                        <input type="date" wire:model="billingForm.effective_date" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Payer Provider ID</label>
                        <input type="text" wire:model="billingForm.payer_provider_id" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Group ID</label>
                        <input type="text" wire:model="billingForm.payer_group_id" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">EFT Status</label>
                        <input type="text" wire:model="billingForm.eft_status" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">ERA Status</label>
                        <input type="text" wire:model="billingForm.era_status" class="form-control form-control-sm">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Billing Notes</label>
                        <textarea wire:model="billingForm.billing_notes" rows="3" class="form-control form-control-sm"></textarea>
                    </div>
                </div>
                <div class="d-flex gap-2 mt-3">
                    <button type="button" wire:click="saveBillingFields" class="btn btn-sm btn-primary">Save</button>
                    <button type="button" wire:click="notifyBilling" class="btn btn-sm btn-outline-success">Notify Billing</button>
                </div>

            @elseif ($activeTab === 'timeline')
                <div class="row g-4">
                    <div class="col-lg-7">
                        <h6 class="fw-semibold mb-3">Activity Timeline</h6>
                        <x-admin.activity-timeline :activities="$case->activities" />
                    </div>
                    <div class="col-lg-5">
                        <h6 class="fw-semibold mb-3">Status History</h6>
                        @forelse($case->statusHistories as $history)
                            <div class="mb-2 pb-2 border-bottom small">
                                <span class="fw-semibold">{{ $history->status?->name ?? 'N/A' }}</span>
                                <span class="text-muted"> · {{ $history->created_at->format('m/d/Y') }}</span>
                                @if($history->notes)<p class="text-muted mb-0 mt-1">{{ $history->notes }}</p>@endif
                            </div>
                        @empty
                            <p class="text-muted small mb-0">No status history yet.</p>
                        @endforelse
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
