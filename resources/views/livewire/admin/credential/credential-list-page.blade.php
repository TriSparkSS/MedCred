<div class="container-fluid flex-grow-1 px-3 px-md-4 py-3 py-md-4">
        {{-- Header --}}
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-start gap-3">
                <div>
                    <h3 class="fw-bold text-primary mb-1">Credentialing Tracker</h3>
                    <p class="text-muted mb-0">Track payer enrollment applications, status, and follow-ups.</p>
                </div>
                <a href="{{ route('admin.credentials.create') }}" class="btn btn-primary">
                    <i class="ti tabler-plus me-1"></i>New Application
                </a>
            </div>
        </div>

        {{-- KPI Stats --}}
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-lg-3">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <p class="text-uppercase text-muted small mb-1">Active Applications</p>
                        <h3 class="fw-bold mb-0">{{ $stats['total_active'] }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card shadow-sm border-0 h-100" wire:click="setFilterCategory('provider')" style="cursor:pointer">
                    <div class="card-body {{ $filterCategory === 'provider' ? 'border border-warning' : '' }}">
                        <p class="text-uppercase text-muted small mb-1">Pending Provider</p>
                        <h3 class="fw-bold mb-0 text-warning">{{ $stats['pending_provider'] }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card shadow-sm border-0 h-100" wire:click="setFilterCategory('payer')" style="cursor:pointer">
                    <div class="card-body {{ $filterCategory === 'payer' ? 'border border-info' : '' }}">
                        <p class="text-uppercase text-muted small mb-1">Pending Payer</p>
                        <h3 class="fw-bold mb-0">{{ $stats['pending_payer'] }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card shadow-sm border-0 h-100" wire:click="setFilterCategory('overdue')" style="cursor:pointer">
                    <div class="card-body {{ $filterCategory === 'overdue' ? 'border border-danger' : '' }}">
                        <p class="text-uppercase text-muted small mb-1">Overdue</p>
                        <h3 class="fw-bold mb-0 text-danger">{{ $stats['overdue'] }}</h3>
                    </div>
                </div>
            </div>
        </div>

        {{-- Filters --}}
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label small text-muted mb-1">Search</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="ti tabler-search"></i></span>
                            <input type="search" wire:model.live.debounce.300ms="search" class="form-control"
                                placeholder="Case #, provider, practice, NPI, payer...">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-muted mb-1">Payer</label>
                        <select wire:model.live="filterPayerId" class="form-select">
                            <option value="">All Payers</option>
                            @foreach ($payers as $payer)
                                <option value="{{ $payer->id }}">{{ $payer->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-muted mb-1">Status</label>
                        <select wire:model.live="filterStatusId" class="form-select">
                            <option value="">All Statuses</option>
                            @foreach ($statuses as $status)
                                <option value="{{ $status->id }}">{{ $status->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="button" wire:click="clearFilters"
                            class="btn btn-outline-secondary w-100"
                            @disabled(! ($search || $filterCategory || $filterPayerId || $filterStatusId))>
                            Clear
                        </button>
                    </div>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
                    <button type="button" wire:click="setFilterCategory('escalated')" class="btn btn-sm {{ $filterCategory === 'escalated' ? 'btn-danger' : 'btn-outline-danger' }}">Escalated</button>
                    <button type="button" wire:click="setFilterCategory('internal')" class="btn btn-sm {{ $filterCategory === 'internal' ? 'btn-primary' : 'btn-outline-primary' }}">Internal</button>
                    <button type="button" wire:click="setFilterCategory('approved')" class="btn btn-sm {{ $filterCategory === 'approved' ? 'btn-success' : 'btn-outline-success' }}">Approved</button>
                    @if ($search || $filterCategory || $filterPayerId || $filterStatusId)
                        <span class="text-muted small ms-auto">
                            {{ $cases->total() }} result{{ $cases->total() !== 1 ? 's' : '' }}
                        </span>
                    @endif
                </div>
            </div>
        </div>

        {{-- Applications Table --}}
        <div class="card shadow-sm border-0" wire:loading.class="opacity-50" wire:target="search,filterPayerId,filterStatusId,filterCategory,clearFilters,setFilterCategory">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Case #</th>
                            <th>Provider</th>
                            <th>Payer</th>
                            <th>Status</th>
                            <th>Assigned To</th>
                            <th>Delay Type</th>
                            <th>Aging</th>
                            <th>Next Follow-up</th>
                            <th>Tasks</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($cases as $case)
                            @php
                                $isOverdue = $case->isOverdue();
                            @endphp
                            <tr wire:key="case-row-{{ $case->id }}" class="{{ $case->is_escalated ? 'table-danger' : ($isOverdue ? 'table-warning' : '') }}">
                                <td>
                                    <span class="fw-semibold">{{ $case->case_number }}</span>
                                    @if($case->is_escalated)<span class="badge bg-danger ms-1">Escalated</span>@endif
                                </td>
                                <td>
                                    <a href="{{ route('admin.providers.show', $case->provider_id) }}" class="fw-semibold">
                                        {{ $case->provider->user->name ?? 'N/A' }}
                                    </a>
                                    <small class="text-muted d-block">{{ $case->practice->legal_name ?? '' }}</small>
                                </td>
                                <td>{{ $case->payer->name ?? 'N/A' }}</td>
                                <td style="min-width: 200px;">
                                    <select class="form-select form-select-sm" wire:change="updateCaseStatus({{ $case->id }}, $event.target.value)">
                                        @foreach ($statuses as $status)
                                            <option value="{{ $status->id }}" @selected($case->status_id == $status->id)>{{ $status->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td><small>{{ $case->assignedAdmin->name ?? 'Unassigned' }}</small></td>
                                <td>
                                    @if($case->delayOwner)
                                        <span class="badge bg-label-secondary">{{ $case->delayOwner->name }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td><span class="fw-semibold">{{ $case->aging_days }}d</span></td>
                                <td>
                                    @if($case->next_follow_up_date)
                                        <small class="{{ $isOverdue ? 'text-danger fw-bold' : '' }}">{{ $case->next_follow_up_date->format('m/d/Y') }}</small>
                                    @else
                                        <small class="text-muted">—</small>
                                    @endif
                                </td>
                                <td>
                                    @if($case->open_tasks_count > 0)
                                        <a href="{{ route('admin.tasks.kanban', ['case_id' => $case->id]) }}"
                                            class="badge bg-label-warning text-decoration-none">
                                            {{ $case->open_tasks_count }} open
                                        </a>
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <button type="button" wire:click="openDrawer({{ $case->id }})" class="btn btn-sm btn-outline-primary" title="View details">
                                        <i class="ti tabler-eye"></i>
                                    </button>
                                    <button type="button" wire:click="toggleEscalation({{ $case->id }})" class="btn btn-sm btn-outline-danger" title="Toggle escalation">
                                        <i class="ti tabler-alert-triangle"></i>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center py-5 text-muted">
                                    <i class="ti tabler-clipboard-off d-block mb-2" style="font-size:2rem"></i>
                                    No applications found. <a href="{{ route('admin.credentials.create') }}">Create one</a>.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($cases->hasPages())
                <div class="card-footer bg-white py-2">{{ $cases->withQueryString()->links() }}</div>
            @endif
        </div>

        {{-- Detail Drawer --}}
        @if ($showDrawer && $selectedCase)
            <div class="offcanvas offcanvas-end show" tabindex="-1" style="visibility:visible; width: 480px; z-index: 1090;">
                <div class="offcanvas-header border-bottom">
                    <div>
                        <h5 class="offcanvas-title fw-bold">{{ $selectedCase->case_number }}</h5>
                        <small class="text-muted">{{ $selectedCase->provider->user->name ?? '' }} · {{ $selectedCase->payer->name ?? '' }}</small>
                    </div>
                    <button type="button" class="btn-close" wire:click="closeDrawer"></button>
                </div>
                <div class="offcanvas-body">
                    {{-- Status update --}}
                    <div class="mb-4">
                        <label class="form-label fw-medium">Status</label>
                        <select wire:model="drawerStatusId" class="form-select mb-2">
                            @foreach ($statuses as $status)
                                <option value="{{ $status->id }}">{{ $status->name }}</option>
                            @endforeach
                        </select>
                        <button type="button" wire:click="saveDrawerStatus" class="btn btn-sm btn-primary">Update Status</button>
                    </div>

                    {{-- Case info --}}
                    <div class="mb-4">
                        <h6 class="fw-semibold mb-2">Case Details</h6>
                        <div class="row g-2 small">
                            <div class="col-6"><span class="text-muted">Practice:</span> {{ $selectedCase->practice->legal_name ?? 'N/A' }}</div>
                            <div class="col-6"><span class="text-muted">State:</span> {{ $selectedCase->state ?: 'N/A' }}</div>
                            <div class="col-6"><span class="text-muted">Intake:</span> {{ $selectedCase->intake_date?->format('m/d/Y') ?: 'N/A' }}</div>
                            <div class="col-6"><span class="text-muted">Submitted:</span> {{ $selectedCase->submission_date?->format('m/d/Y') ?: 'N/A' }}</div>
                            <div class="col-6"><span class="text-muted">Effective:</span> {{ $selectedCase->effective_date?->format('m/d/Y') ?: 'N/A' }}</div>
                            <div class="col-6"><span class="text-muted">Aging:</span> {{ $selectedCase->aging_days }} days</div>
                        </div>
                    </div>

                    {{-- Document Checklist --}}
                    @php $checklist = $selectedCase->checklist_completion; @endphp
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="fw-semibold mb-0">Document Checklist</h6>
                            <a href="{{ route('admin.credentials.packet', $selectedCase) }}"
                                class="btn btn-sm btn-outline-primary" target="_blank">
                                <i class="ti tabler-package me-1"></i>Download Packet
                            </a>
                        </div>
                        @if ($checklist['total'] > 0)
                            <div class="mb-3">
                                <div class="d-flex justify-content-between small mb-1">
                                    <span>{{ $checklist['received'] }} of {{ $checklist['total'] }} received</span>
                                    <span class="fw-semibold">{{ $checklist['percent'] }}%</span>
                                </div>
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar bg-success" style="width: {{ $checklist['percent'] }}%"></div>
                                </div>
                            </div>
                            @foreach ($selectedCase->documentItems as $item)
                                <div class="d-flex align-items-center gap-2 mb-2 small">
                                    <i class="ti tabler-{{ $item->is_received ? 'circle-check text-success' : 'circle-dashed text-muted' }}"></i>
                                    <span class="{{ $item->is_received ? '' : 'text-muted' }}">
                                        {{ $item->documentType->name ?? 'Document' }}
                                        @if ($item->is_required)<span class="text-danger">*</span>@endif
                                    </span>
                                </div>
                            @endforeach
                        @else
                            <p class="text-muted small mb-0">No checklist items. Configure payer document requirements or upload documents linked to this case.</p>
                        @endif
                    </div>

                    {{-- Tasks & Follow-ups --}}
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="fw-semibold mb-0">Tasks & Follow-ups</h6>
                            <a href="{{ route('admin.tasks.kanban', ['case_id' => $selectedCase->id]) }}"
                                class="btn btn-sm btn-outline-primary">
                                <i class="ti tabler-layout-kanban me-1"></i>View in Kanban
                            </a>
                        </div>
                        @forelse($selectedCase->tasks as $task)
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
                                        {{ $task->assignedAdmin->name ?? 'Unassigned' }}
                                        · {{ $task->due_date?->format('m/d/Y') ?: 'No due date' }}
                                    </small>
                                </div>
                                @if(! $task->isCompleted())
                                    <button type="button" wire:click="completeDrawerTask({{ $task->id }})"
                                        class="btn btn-sm btn-outline-success" title="Mark complete">
                                        <i class="ti tabler-check"></i>
                                    </button>
                                @endif
                            </div>
                        @empty
                            <p class="text-muted small mb-2">No tasks linked to this case.</p>
                        @endforelse
                        <div class="mt-3 pt-3 border-top">
                            <label class="form-label small fw-medium mb-1">Create Follow-up Task</label>
                            <input type="text" wire:model="newTaskTitle" class="form-control form-control-sm mb-2"
                                placeholder="Task title...">
                            @error('newTaskTitle')<div class="text-danger small">{{ $message }}</div>@enderror
                            <div class="row g-2 mb-2">
                                <div class="col-6">
                                    <input type="date" wire:model="newTaskDueDate" class="form-control form-control-sm">
                                </div>
                                <div class="col-6">
                                    <select wire:model="newTaskAssigneeId" class="form-select form-select-sm">
                                        <option value="">Unassigned</option>
                                        @foreach ($admins as $admin)
                                            <option value="{{ $admin->id }}">{{ $admin->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <button type="button" wire:click="createFollowUpTask" class="btn btn-sm btn-primary">
                                <i class="ti tabler-plus me-1"></i>Add Task
                            </button>
                        </div>
                    </div>

                    {{-- Delay owner override --}}
                    <div class="mb-4">
                        <h6 class="fw-semibold mb-2">Delay Ownership</h6>
                        <p class="small text-muted mb-2">Current: <strong>{{ $selectedCase->delayOwner->name ?? 'Unassigned' }}</strong></p>
                        @if ($selectedCase->slaTimers->isNotEmpty())
                            <div class="small text-muted mb-2">
                                @foreach ($selectedCase->slaTimers as $timer)
                                    <div>{{ str_replace('_', ' ', $timer->rule_key) }} — due {{ $timer->due_at->format('m/d/Y') }}</div>
                                @endforeach
                            </div>
                        @endif
                        <select wire:model="drawerDelayOwnerId" class="form-select form-select-sm mb-2">
                            <option value="">Select delay owner...</option>
                            @foreach ($delayOwners as $owner)
                                <option value="{{ $owner->id }}">{{ $owner->name }}</option>
                            @endforeach
                        </select>
                        <input type="text" wire:model="overrideReason" class="form-control form-control-sm mb-2"
                            placeholder="Override reason (required)">
                        @error('overrideReason')<div class="text-danger small">{{ $message }}</div>@enderror
                        <button type="button" wire:click="saveDelayOverride" class="btn btn-sm btn-outline-secondary">Override Delay Owner</button>
                    </div>

                    {{-- Send email --}}
                    <div class="mb-4">
                        <h6 class="fw-semibold mb-2">Send Email</h6>
                        <select wire:model="emailTemplateId" class="form-select form-select-sm mb-2">
                            <option value="">Select template...</option>
                            @foreach ($emailTemplates as $template)
                                <option value="{{ $template->id }}">{{ $template->name }}</option>
                            @endforeach
                        </select>
                        <button type="button" wire:click="sendDocumentRequest" class="btn btn-sm btn-primary">
                            <i class="ti tabler-mail me-1"></i>Send to Provider
                        </button>
                    </div>

                    {{-- Add note --}}
                    <div class="mb-4">
                        <h6 class="fw-semibold mb-2">Add Note</h6>
                        <textarea wire:model="newNote" rows="2" class="form-control mb-2" placeholder="Log a call, follow-up, or note..."></textarea>
                        @error('newNote')<div class="text-danger small">{{ $message }}</div>@enderror
                        <button type="button" wire:click="addNote" class="btn btn-sm btn-outline-primary">Add Note</button>
                    </div>

                    {{-- Timeline --}}
                    <div class="mb-4">
                        <h6 class="fw-semibold mb-3">Activity Timeline</h6>
                        <x-admin.activity-timeline :activities="$selectedCase->activities" />
                    </div>

                    {{-- Status History --}}
                    <div>
                        <h6 class="fw-semibold mb-3">Status History</h6>
                        @foreach($selectedCase->statusHistories as $history)
                            <div class="mb-2 pb-2 border-bottom small">
                                <span class="fw-semibold">{{ $history->status->name ?? 'N/A' }}</span>
                                <span class="text-muted"> · {{ $history->created_at->format('m/d/Y') }}</span>
                                @if($history->notes)<p class="text-muted mb-0 mt-1">{{ $history->notes }}</p>@endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="offcanvas-backdrop fade show" wire:click="closeDrawer" style="z-index: 1080;"></div>
        @endif
</div>
