@php
    $checklist = $case->checklist_completion;
    $openTaskCount = $case->tasks
        ->filter(fn ($task) => ! $task->isCompleted() && $task->status?->value !== 'cancelled')
        ->count();
    $linkedMessageCount = $case->emailCaseLinks->count();
    $statusCategory = $case->status?->dashboard_category;
    $statusBadge = match ($statusCategory) {
        'approved' => 'success',
        'closed' => 'secondary',
        'payer' => 'info',
        'provider' => 'warning',
        'practice' => 'warning',
        default => 'primary',
    };
    $agingTone = $case->aging_days >= 90 ? 'text-danger' : ($case->aging_days >= 45 ? 'text-warning' : 'text-primary');
    $followUpOverdue = $case->isOverdue();
    $providerName = $case->provider?->user?->name ?? 'N/A';
    $providerInitial = strtoupper(substr($providerName !== 'N/A' ? $providerName : 'C', 0, 1));
    $detailNotes = $case->activities
        ->where('activity_type', 'note')
        ->sortByDesc('id')
        ->values();

    $detailFields = [
        ['label' => 'Practice', 'value' => $case->practice?->legal_name],
        ['label' => 'Location', 'value' => $case->location?->name],
        ['label' => 'State', 'value' => $case->state],
        ['label' => 'Case type', 'value' => $case->caseType?->name],
        ['label' => 'Intake', 'value' => $case->intake_date?->format('m/d/Y')],
        ['label' => 'Submitted', 'value' => $case->submission_date?->format('m/d/Y')],
        ['label' => 'Effective', 'value' => $case->effective_date?->format('m/d/Y')],
        ['label' => 'Priority', 'value' => $case->priority?->name],
        ['label' => 'Assignee', 'value' => $case->assignedAdmin?->name ?? 'Unassigned'],
        ['label' => 'Last action', 'value' => $case->last_action_at?->format('m/d/Y g:i A')],
    ];
@endphp

<div class="container-fluid px-3 px-md-4 py-4">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body d-flex flex-column flex-lg-row justify-content-between align-items-start gap-3">
            <div class="d-flex align-items-start gap-3">
                <span class="avatar rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center flex-shrink-0"
                    style="width: 52px; height: 52px;">
                    {{ $providerInitial }}
                </span>
                <div>
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                        <h4 class="fw-bold text-primary mb-0">{{ $case->case_number }}</h4>
                        @if ($case->is_escalated)
                            <span class="badge bg-danger">Escalated</span>
                        @endif
                        @if ($case->status)
                            <span class="badge bg-label-{{ $statusBadge }}">{{ $case->status->name }}</span>
                        @endif
                        @if ($case->priority)
                            <span class="badge bg-label-secondary">{{ $case->priority->name }}</span>
                        @endif
                    </div>
                    <p class="text-muted mb-1">
                        @if ($case->provider)
                            <a href="{{ route('admin.providers.show', $case->provider) }}" class="text-decoration-none">{{ $providerName }}</a>
                        @else
                            {{ $providerName }}
                        @endif
                        · {{ $case->payer?->name ?? 'N/A' }}
                        @if ($case->practice)
                            ·
                            <a href="{{ route('admin.practices.show', $case->practice) }}" class="text-decoration-none">{{ $case->practice->legal_name }}</a>
                        @endif
                    </p>
                    <div class="d-flex flex-wrap gap-3 small text-muted">
                        <span><i class="ti tabler-user-check me-1"></i>{{ $case->assignedAdmin?->name ?? 'Unassigned' }}</span>
                        <span><i class="ti tabler-clock me-1"></i>{{ $case->aging_days }} days aging</span>
                        @if ($case->next_follow_up_date)
                            <span class="{{ $followUpOverdue ? 'text-danger' : '' }}">
                                <i class="ti tabler-calendar-event me-1"></i>Follow-up {{ $case->next_follow_up_date->format('m/d/Y') }}
                            </span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                @if ($canEscalateTasks)
                    <button type="button"
                        wire:click="toggleEscalation"
                        class="btn {{ $case->is_escalated ? 'btn-danger' : 'btn-outline-danger' }}">
                        <i class="ti tabler-flag me-1"></i>{{ $case->is_escalated ? 'De-escalate' : 'Escalate' }}
                    </button>
                @endif
                <a href="{{ route('admin.credentials') }}" class="btn btn-outline-secondary">
                    <i class="ti tabler-arrow-left me-1"></i>Back to Tracker
                </a>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <p class="text-uppercase text-muted small mb-1">Aging</p>
                    <h4 class="fw-bold mb-0 {{ $agingTone }}">{{ $case->aging_days }} <span class="fs-6 fw-semibold">days</span></h4>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <p class="text-uppercase text-muted small mb-1">Next follow-up</p>
                    <h4 class="fw-bold mb-0 {{ $followUpOverdue ? 'text-danger' : '' }}">
                        {{ $case->next_follow_up_date?->format('m/d/Y') ?: '—' }}
                    </h4>
                    @if ($followUpOverdue)
                        <small class="text-danger">Overdue</small>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <p class="text-uppercase text-muted small mb-1">Delay owner</p>
                    <h5 class="fw-semibold mb-0">{{ $case->delayOwner?->name ?? 'Unassigned' }}</h5>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <p class="text-uppercase text-muted small mb-1">Documents</p>
                    <h4 class="fw-bold mb-0">
                        {{ $checklist['total'] > 0 ? $checklist['percent'].'%' : '—' }}
                    </h4>
                    @if ($checklist['total'] > 0)
                        <small class="text-muted">{{ $checklist['received'] }} of {{ $checklist['total'] }} received</small>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <ul class="nav nav-tabs mb-4 flex-nowrap overflow-auto">
        @foreach ([
            'details' => 'Details',
            'documents' => 'Documents',
            'communication' => 'Communication',
            'tasks' => 'Tasks',
            'billing' => 'Billing',
            'timeline' => 'Timeline',
        ] as $key => $label)
            <li class="nav-item">
                <button type="button" class="nav-link {{ $activeTab === $key ? 'active' : '' }}" wire:click="setTab('{{ $key }}')">
                    {{ $label }}
                    @if ($key === 'documents' && $checklist['total'] > 0)
                        <span class="badge bg-label-primary ms-1">{{ $checklist['received'] }}/{{ $checklist['total'] }}</span>
                    @elseif ($key === 'communication' && $linkedMessageCount)
                        <span class="badge bg-label-primary ms-1">{{ $linkedMessageCount }}</span>
                    @elseif ($key === 'tasks' && $openTaskCount)
                        <span class="badge bg-warning ms-1">{{ $openTaskCount }}</span>
                    @endif
                </button>
            </li>
        @endforeach
    </ul>

    @if ($activeTab === 'details')
        <div class="row g-4">
            <div class="col-xl-7">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0 fw-semibold">Status</h6>
                            <small class="text-muted">Update the case pipeline stage</small>
                        </div>
                        @if ($case->status)
                            <span class="badge bg-label-{{ $statusBadge }}">{{ $case->status->name }}</span>
                        @endif
                    </div>
                    <div class="card-body">
                        @if ($canEditCredentials)
                            <div class="row g-2 align-items-end">
                                <div class="col-md-8">
                                    <label class="form-label small text-muted mb-1">Case status</label>
                                    <select wire:model="statusId" class="form-select">
                                        @foreach ($statuses as $status)
                                            <option value="{{ $status->id }}">{{ $status->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <button type="button" wire:click="saveStatus" class="btn btn-primary w-100">
                                        Update Status
                                    </button>
                                </div>
                            </div>
                        @else
                            <span class="badge bg-label-secondary">{{ $case->status?->name ?? 'N/A' }}</span>
                        @endif
                    </div>
                </div>

                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white">
                        <h6 class="mb-0 fw-semibold">Case Details</h6>
                        <small class="text-muted">Key dates, assignment, and facility context</small>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            @foreach ($detailFields as $field)
                                <div class="col-sm-6">
                                    <div class="border rounded-3 p-3 h-100">
                                        <div class="text-muted text-uppercase small mb-1">{{ $field['label'] }}</div>
                                        <div class="fw-semibold">{{ $field['value'] ?: 'N/A' }}</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-5">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white">
                        <h6 class="mb-0 fw-semibold">Delay Ownership</h6>
                        <small class="text-muted">Who currently owns the wait, and SLA timers</small>
                    </div>
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <span class="badge bg-label-warning">Current</span>
                            <span class="fw-semibold">{{ $case->delayOwner?->name ?? 'Unassigned' }}</span>
                        </div>
                        @if ($case->slaTimers->isNotEmpty())
                            <div class="mb-3">
                                @foreach ($case->slaTimers as $timer)
                                    <div class="d-flex justify-content-between small border rounded px-3 py-2 mb-2">
                                        <span>{{ str_replace('_', ' ', $timer->rule_key) }}</span>
                                        <span class="text-muted">due {{ $timer->due_at->format('m/d/Y') }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                        @if ($canOverrideDelay)
                            <label class="form-label small text-muted mb-1">Select delay owner</label>
                            <select wire:model="delayOwnerId" class="form-select mb-2">
                                <option value="">Select delay owner...</option>
                                @foreach ($delayOwners as $owner)
                                    <option value="{{ $owner->id }}">{{ $owner->name }}</option>
                                @endforeach
                            </select>
                            <input type="text" wire:model="overrideReason" class="form-control mb-2" placeholder="Override reason (required)">
                            @error('overrideReason')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
                            <button type="button" wire:click="saveDelayOverride" class="btn btn-outline-secondary">
                                Override Delay Owner
                            </button>
                        @endif
                    </div>
                </div>

                @if ($canEditCredentials)
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-header bg-white">
                            <h6 class="mb-0 fw-semibold">Notes</h6>
                            <small class="text-muted">Logged notes also appear on the Timeline tab</small>
                        </div>
                        <div class="card-body">
                            <textarea wire:model="newNote" rows="3" class="form-control mb-2" placeholder="Log a call, follow-up, or note..."></textarea>
                            @error('newNote')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
                            <button type="button" wire:click="addNote" class="btn btn-outline-primary mb-3">Add Note</button>

                            <div class="border rounded-3 p-3" style="max-height: 260px; overflow-y: auto;">
                                @forelse ($detailNotes as $activity)
                                    <div class="d-flex gap-2 mb-3 {{ ! $loop->last ? 'pb-3 border-bottom' : '' }}">
                                        <span class="avatar avatar-sm rounded-circle bg-label-primary text-primary fw-semibold d-flex align-items-center justify-content-center flex-shrink-0"
                                            style="width: 32px; height: 32px;">
                                            {{ strtoupper(substr($activity->admin?->name ?? $activity->user?->name ?? 'S', 0, 1)) }}
                                        </span>
                                        <div class="small">
                                            <div class="text-muted mb-1">
                                                {{ $activity->admin?->name ?? $activity->user?->name ?? 'System' }}
                                                · {{ $activity->created_at?->format('m/d/Y g:i A') }}
                                            </div>
                                            <div>{{ $activity->summary }}</div>
                                        </div>
                                    </div>
                                @empty
                                    <p class="text-muted small mb-0">No notes yet. Notes added here also appear on the Timeline tab.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                @else
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-header bg-white">
                            <h6 class="mb-0 fw-semibold">Notes</h6>
                        </div>
                        <div class="card-body">
                            <div class="border rounded-3 p-3" style="max-height: 260px; overflow-y: auto;">
                                @forelse ($detailNotes as $activity)
                                    <div class="mb-2 pb-2 {{ ! $loop->last ? 'border-bottom' : '' }} small">
                                        <div class="text-muted">
                                            {{ $activity->admin?->name ?? $activity->user?->name ?? 'System' }}
                                            · {{ $activity->created_at?->format('m/d/Y g:i A') }}
                                        </div>
                                        <div>{{ $activity->summary }}</div>
                                    </div>
                                @empty
                                    <p class="text-muted small mb-0">No notes yet. Notes added here also appear on the Timeline tab.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                @endif

                @if ($canManageTasks)
                    <div class="card shadow-sm border-0 border-primary border-opacity-25">
                        <div class="card-header bg-white">
                            <h6 class="mb-0 fw-semibold">Create follow-up</h6>
                            <small class="text-muted">Sets the case follow-up date and creates a follow-up task.</small>
                        </div>
                        <div class="card-body">
                            <input type="text" wire:model="newTaskTitle" class="form-control mb-3" placeholder="Follow-up title (optional)">
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label small mb-1">Follow-up date</label>
                                    <input type="date" wire:model="newTaskDueDate" class="form-control">
                                </div>
                                <div class="col-6">
                                    <label class="form-label small mb-1">Assign to</label>
                                    <select wire:model="newTaskAssigneeId" class="form-select">
                                        <option value="">Unassigned</option>
                                        @foreach ($admins as $admin)
                                            <option value="{{ $admin->id }}">{{ $admin->displayLabel() }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <button type="button" wire:click="createFollowUpFromDetails" class="btn btn-primary">
                                <i class="ti tabler-calendar-plus me-1"></i>Create follow-up
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        </div>

    @elseif ($activeTab === 'documents')
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-0 fw-semibold">Document Checklist</h6>
                    <small class="text-muted">Required files for this payer application</small>
                </div>
                <a href="{{ route('admin.credentials.packet', $case) }}"
                    class="btn btn-sm btn-outline-primary" target="_blank">
                    <i class="ti tabler-package me-1"></i>Download Packet
                </a>
            </div>
            <div class="card-body">
                @if ($checklist['total'] > 0)
                    <div class="mb-4" style="max-width: 420px;">
                        <div class="d-flex justify-content-between small mb-1">
                            <span>{{ $checklist['received'] }} of {{ $checklist['total'] }} received</span>
                            <span class="fw-semibold">{{ $checklist['percent'] }}%</span>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-success" style="width: {{ $checklist['percent'] }}%"></div>
                        </div>
                    </div>
                    <div class="row g-2">
                        @foreach ($case->documentItems as $item)
                            <div class="col-md-6">
                                <div class="d-flex align-items-center gap-2 p-3 border rounded-3">
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
            </div>
        </div>

    @elseif ($activeTab === 'communication')
        <div class="row g-4">
            <div class="col-lg-5">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white">
                        <h6 class="mb-0 fw-semibold">Send Email</h6>
                        <small class="text-muted">Request documents from the provider</small>
                    </div>
                    <div class="card-body">
                        @if ($canSendEmails)
                            <select wire:model="emailTemplateId" class="form-select mb-2">
                                <option value="">Select template...</option>
                                @foreach ($emailTemplates as $template)
                                    <option value="{{ $template->id }}">{{ $template->name }}</option>
                                @endforeach
                            </select>
                            @error('emailTemplateId')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
                            <button type="button" wire:click="sendDocumentRequest" class="btn btn-primary mb-3">
                                <i class="ti tabler-mail me-1"></i>Send to Provider
                            </button>
                        @endif
                        <div>
                            <a href="{{ route('admin.email.dashboard') }}" class="btn btn-outline-secondary">
                                <i class="ti tabler-mail me-1"></i>Open Email Center
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white">
                        <h6 class="mb-0 fw-semibold">Linked Messages</h6>
                    </div>
                    <div class="card-body">
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
            </div>
        </div>

    @elseif ($activeTab === 'tasks')
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold">Tasks & Follow-ups</h6>
                <a href="{{ route('admin.tasks.kanban', ['case_id' => $case->id]) }}"
                    class="btn btn-sm btn-outline-primary">
                    <i class="ti tabler-layout-kanban me-1"></i>View in Kanban
                </a>
            </div>
            <div class="card-body">
                @if (count($selectedTaskIds) > 0)
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-3 p-2 border rounded bg-light">
                        <span class="small fw-semibold">{{ count($selectedTaskIds) }} selected</span>
                        @if ($canAssignTasks)
                            <select wire:model="bulkAssigneeId" class="form-select form-select-sm" style="width: auto; min-width: 150px;">
                                <option value="">Unassigned</option>
                                @foreach ($admins as $admin)
                                    <option value="{{ $admin->id }}">{{ $admin->displayLabel() }}</option>
                                @endforeach
                            </select>
                            <button type="button" wire:click="bulkAssignSelected" class="btn btn-sm btn-primary">Assign</button>
                        @endif
                        @if ($canManageTasks)
                            <button type="button" wire:click="bulkCompleteSelected" class="btn btn-sm btn-outline-success">Complete</button>
                            <button type="button" wire:click="bulkCancelSelected" class="btn btn-sm btn-outline-secondary">Cancel</button>
                        @endif
                        <button type="button" wire:click="clearTaskSelection" class="btn btn-sm btn-link">Clear</button>
                    </div>
                @endif
                <div class="row g-4">
                    <div class="col-lg-7">
                        @forelse($case->tasks as $task)
                            <div class="d-flex justify-content-between align-items-start gap-2 mb-2 p-3 border rounded-3 {{ $task->isCompleted() ? 'bg-light' : '' }}">
                                <div class="form-check mt-1">
                                    <input type="checkbox" class="form-check-input" value="{{ $task->id }}"
                                        wire:model.live="selectedTaskIds">
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <span class="badge bg-label-secondary">{{ $taskTypeLabel($task->task_type) }}</span>
                                        @if($task->isCompleted())
                                            <span class="badge bg-label-success">Done</span>
                                        @elseif($task->status?->value === 'cancelled')
                                            <span class="badge bg-label-secondary">Cancelled</span>
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
                                @if(! $task->isCompleted() && $task->status?->value !== 'cancelled' && $canManageTasks)
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
                    @if ($canManageTasks)
                    <div class="col-lg-5">
                        <div class="border rounded-3 p-3">
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
                            <div class="form-check mb-2">
                                <input type="checkbox" class="form-check-input" id="newTaskRepeat" wire:model.live="newTaskRepeat">
                                <label class="form-check-label small" for="newTaskRepeat">Repeat as a series</label>
                            </div>
                            @if ($newTaskRepeat)
                                <div class="row g-2 mb-2">
                                    <div class="col-6">
                                        <label class="form-label small mb-1">Every (days)</label>
                                        <input type="number" min="1" max="90" wire:model="newTaskIntervalDays"
                                            class="form-control form-control-sm">
                                        @error('newTaskIntervalDays')<div class="text-danger small">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small mb-1">Times</label>
                                        <input type="number" min="2" max="12" wire:model="newTaskOccurrences"
                                            class="form-control form-control-sm">
                                        @error('newTaskOccurrences')<div class="text-danger small">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                            @endif
                            <button type="button" wire:click="createFollowUpTask" class="btn btn-sm btn-primary">
                                <i class="ti tabler-plus me-1"></i>{{ $newTaskRepeat ? 'Add series' : 'Add Task' }}
                            </button>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>

    @elseif ($activeTab === 'billing')
        @php $missingBilling = $billingService->missingFields($case); @endphp
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-0 fw-semibold">Billing Readiness</h6>
                    <small class="text-muted">Approval, IDs, and remittance status</small>
                </div>
                @if ($case->ready_to_bill)
                    <span class="badge bg-success">Ready to Bill</span>
                @endif
            </div>
            <div class="card-body">
                @if (! $case->ready_to_bill && count($missingBilling))
                    <div class="alert alert-warning py-2 small">Missing: {{ implode(', ', $missingBilling) }}</div>
                @endif
                <div class="row g-3" style="max-width: 720px;">
                    <div class="col-md-6">
                        <label class="form-label">Approval Date</label>
                        <input type="date" wire:model="billingForm.approval_date" class="form-control" @disabled(! $canEditCredentials)>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Effective Date</label>
                        <input type="date" wire:model="billingForm.effective_date" class="form-control" @disabled(! $canEditCredentials)>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Payer Provider ID</label>
                        <input type="text" wire:model="billingForm.payer_provider_id" class="form-control" @disabled(! $canEditCredentials)>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Group ID</label>
                        <input type="text" wire:model="billingForm.payer_group_id" class="form-control" @disabled(! $canEditCredentials)>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">EFT Status</label>
                        <input type="text" wire:model="billingForm.eft_status" class="form-control" @disabled(! $canEditCredentials)>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">ERA Status</label>
                        <input type="text" wire:model="billingForm.era_status" class="form-control" @disabled(! $canEditCredentials)>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Billing Notes</label>
                        <textarea wire:model="billingForm.billing_notes" rows="3" class="form-control" @disabled(! $canEditCredentials)></textarea>
                    </div>
                </div>
                <div class="d-flex gap-2 mt-3">
                    @if ($canEditCredentials)
                    <button type="button" wire:click="saveBillingFields" class="btn btn-primary">Save</button>
                    @endif
                    @if ($canNotifyBilling)
                    <button type="button" wire:click="notifyBilling" class="btn btn-outline-success">Notify Billing</button>
                    @endif
                </div>
            </div>
        </div>

    @elseif ($activeTab === 'timeline')
        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white">
                        <h6 class="mb-0 fw-semibold">Activity Timeline</h6>
                    </div>
                    <div class="card-body">
                        <x-admin.activity-timeline :activities="$case->activities" />
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white">
                        <h6 class="mb-0 fw-semibold">Status History</h6>
                    </div>
                    <div class="card-body">
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
            </div>
        </div>
    @endif
</div>
