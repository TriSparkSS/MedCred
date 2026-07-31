<div class="container-fluid flex-grow-1 px-3 px-md-4 py-3 py-md-4">
        {{-- Header --}}
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-start gap-3">
                <div>
                    <h3 class="fw-bold text-primary mb-1">Credentialing Tracker</h3>
                    <p class="text-muted mb-0">Track payer enrollment applications, status, and follow-ups.</p>
                </div>
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
                    <div class="col-md-3">
                        <label class="form-label small text-muted mb-1">Search</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="ti tabler-search"></i></span>
                            <input type="search" wire:model.live.debounce.300ms="caseSearch" class="form-control"
                                placeholder="Case #, provider, practice, NPI, payer..."
                                autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-muted mb-1">Practice</label>
                        <select wire:model.live="filterPracticeId" class="form-select">
                            <option value="">All Practices</option>
                            @foreach ($practices as $practice)
                                <option value="{{ $practice->id }}">
                                    {{ $practice->legal_name }}@if($practice->client_code) ({{ $practice->client_code }})@endif
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-muted mb-1">Provider</label>
                        <select wire:model.live="filterProviderId" class="form-select" @disabled(! $filterPracticeId)>
                            <option value="">{{ $filterPracticeId ? 'All Providers' : 'Select a practice first' }}</option>
                            @foreach ($providers as $provider)
                                <option value="{{ $provider->id }}">{{ $provider->user->name ?? 'Provider #'.$provider->id }}</option>
                            @endforeach
                        </select>
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
                </div>
                <div class="row g-3 align-items-end mt-1">
                    <div class="col-md-3">
                        <label class="form-label small text-muted mb-1">Status</label>
                        <select wire:model.live="filterStatusId" class="form-select">
                            <option value="">All Statuses</option>
                            @foreach ($statuses as $status)
                                <option value="{{ $status->id }}">{{ $status->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-muted mb-1">Assigned To (Staff)</label>
                        <select wire:model.live="filterOwnerId" class="form-select">
                            <option value="">All Owners</option>
                            @foreach ($admins as $admin)
                                <option value="{{ $admin->id }}">{{ $admin->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small text-muted mb-1">State</label>
                        <input type="text" wire:model.live.debounce.300ms="filterState" class="form-control" placeholder="e.g. TX" maxlength="2">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small text-muted mb-1">Revalidation Due</label>
                        <select wire:model.live="filterRevalidation" class="form-select">
                            <option value="">Any</option>
                            <option value="30">Next 30 days</option>
                            <option value="60">Next 60 days</option>
                            <option value="90">Next 90 days</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="button" wire:click="clearFilters"
                            class="btn btn-outline-secondary w-100"
                            @disabled(! $this->hasActiveFilters())>
                            Clear
                        </button>
                    </div>
                </div>
                <div class="row g-3 align-items-end mt-1">
                    <div class="col-md-3 d-flex align-items-end">
                        <div class="form-check mb-2">
                            <input type="checkbox" wire:model.live="filterRecentlySubmitted" class="form-check-input" id="recentSubmitted">
                            <label class="form-check-label small" for="recentSubmitted">Recently submitted (14d)</label>
                        </div>
                    </div>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
                    <button type="button" wire:click="setFilterCategory('escalated')" class="btn btn-sm {{ $filterCategory === 'escalated' ? 'btn-danger' : 'btn-outline-danger' }}">Escalated</button>
                    <button type="button" wire:click="setFilterCategory('internal')" class="btn btn-sm {{ $filterCategory === 'internal' ? 'btn-primary' : 'btn-outline-primary' }}">Internal</button>
                    <button type="button" wire:click="setFilterCategory('approved')" class="btn btn-sm {{ $filterCategory === 'approved' ? 'btn-success' : 'btn-outline-success' }}">Approved</button>
                    @if ($this->hasActiveFilters())
                        <span class="text-muted small ms-auto">
                            {{ $cases->total() }} result{{ $cases->total() !== 1 ? 's' : '' }}
                        </span>
                    @endif
                </div>
            </div>
        </div>

        {{-- Applications Table --}}
        <div class="card shadow-sm border-0" wire:loading.class="opacity-50" wire:target="caseSearch,filterPayerId,filterPracticeId,filterProviderId,filterStatusId,filterOwnerId,filterState,filterRevalidation,filterRecentlySubmitted,filterCategory,clearFilters,setFilterCategory,updateCaseStatus,inlineUpdate">
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
                    <tbody wire:key="case-rows-{{ md5($caseSearch.$filterPayerId.$filterPracticeId.$filterProviderId.$filterStatusId.$filterCategory.$filterOwnerId.$filterState.$cases->currentPage()) }}">
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
                                        {{ $case->provider?->user?->name ?? 'N/A' }}
                                    </a>
                                    <small class="text-muted d-block">{{ $case->practice?->legal_name ?? '' }}</small>
                                </td>
                                <td>{{ $case->payer?->name ?? 'N/A' }}</td>
                                <td style="min-width: 200px;">
                                    <select class="form-select form-select-sm" wire:change="updateCaseStatus({{ $case->id }}, $event.target.value)">
                                        @foreach ($statuses as $status)
                                            <option value="{{ $status->id }}" @selected($case->status_id == $status->id)>{{ $status->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td style="min-width: 140px;">
                                    <select class="form-select form-select-sm" wire:change="inlineUpdate({{ $case->id }}, 'assigned_admin_id', $event.target.value)">
                                        <option value="">Unassigned</option>
                                        @foreach ($admins as $admin)
                                            <option value="{{ $admin->id }}" @selected($case->assigned_admin_id == $admin->id)>{{ $admin->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td style="min-width: 130px;">
                                    <select class="form-select form-select-sm" wire:change="inlineUpdate({{ $case->id }}, 'delay_owner_id', $event.target.value)">
                                        <option value="">—</option>
                                        @foreach ($delayOwners as $owner)
                                            <option value="{{ $owner->id }}" @selected($case->delay_owner_id == $owner->id)>{{ $owner->name }}</option>
                                        @endforeach
                                    </select>
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
                                <td class="text-end text-nowrap">
                                    <div class="d-inline-flex gap-1">
                                        <button type="button"
                                            wire:click.stop="openDrawer({{ $case->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="openDrawer({{ $case->id }})"
                                            class="btn btn-sm btn-outline-primary"
                                            title="View application details">
                                            <span wire:loading.remove wire:target="openDrawer({{ $case->id }})">
                                                <i class="ti tabler-eye me-1"></i>View
                                            </span>
                                            <span wire:loading wire:target="openDrawer({{ $case->id }})" class="spinner-border spinner-border-sm" role="status"></span>
                                        </button>
                                        <button type="button"
                                            wire:click.stop="toggleEscalation({{ $case->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="toggleEscalation({{ $case->id }})"
                                            class="btn btn-sm {{ $case->is_escalated ? 'btn-danger' : 'btn-outline-danger' }}"
                                            title="{{ $case->is_escalated ? 'Remove escalation' : 'Escalate application' }}">
                                            <span wire:loading.remove wire:target="toggleEscalation({{ $case->id }})">
                                                <i class="ti tabler-flag me-1"></i>{{ $case->is_escalated ? 'De-escalate' : 'Escalate' }}
                                            </span>
                                            <span wire:loading wire:target="toggleEscalation({{ $case->id }})" class="spinner-border spinner-border-sm" role="status"></span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center py-5 text-muted">
                                    <i class="ti tabler-clipboard-off d-block mb-2" style="font-size:2rem"></i>
                                    No applications found. Create applications from a Practice or Provider detail page.
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

        {{-- Detail Drawer (teleported so Vuexy layout overflow/stacking cannot hide it) --}}
        @if ($showDrawer && $selectedCase)
            @teleport('body')
                <div class="credential-case-drawer" wire:key="case-drawer-{{ $selectedCase->id }}" style="position: fixed; inset: 0; z-index: 1200;">
                    <div class="offcanvas-backdrop fade show" wire:click="closeDrawer" style="z-index: 1200;"></div>
                    <div class="offcanvas offcanvas-end show d-flex flex-column" tabindex="-1"
                        style="visibility: visible; width: min(480px, 100vw); height: 100vh; z-index: 1201; transform: none;">
                        <div class="offcanvas-header border-bottom">
                            <div>
                                <h5 class="offcanvas-title fw-bold">{{ $selectedCase->case_number }}</h5>
                                <small class="text-muted">{{ $selectedCase->provider?->user?->name ?? '' }} · {{ $selectedCase->payer?->name ?? '' }}</small>
                            </div>
                            <button type="button" class="btn-close" wire:click="closeDrawer" aria-label="Close"></button>
                        </div>
                        <div class="offcanvas-body">
                            <ul class="nav nav-pills nav-fill mb-3 small">
                                @foreach (['details' => 'Details', 'documents' => 'Docs', 'communication' => 'Comms', 'tasks' => 'Tasks', 'billing' => 'Billing', 'timeline' => 'Timeline'] as $key => $label)
                                    <li class="nav-item">
                                        <button type="button" class="nav-link {{ $drawerTab === $key ? 'active' : '' }}" wire:click="setDrawerTab('{{ $key }}')">{{ $label }}</button>
                                    </li>
                                @endforeach
                            </ul>

                            @if ($drawerTab === 'details')
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
                                    <div class="col-6"><span class="text-muted">Practice:</span> {{ $selectedCase->practice?->legal_name ?? 'N/A' }}</div>
                                    <div class="col-6"><span class="text-muted">State:</span> {{ $selectedCase->state ?: 'N/A' }}</div>
                                    <div class="col-6"><span class="text-muted">Intake:</span> {{ $selectedCase->intake_date?->format('m/d/Y') ?: 'N/A' }}</div>
                                    <div class="col-6"><span class="text-muted">Submitted:</span> {{ $selectedCase->submission_date?->format('m/d/Y') ?: 'N/A' }}</div>
                                    <div class="col-6"><span class="text-muted">Effective:</span> {{ $selectedCase->effective_date?->format('m/d/Y') ?: 'N/A' }}</div>
                                    <div class="col-6"><span class="text-muted">Aging:</span> {{ $selectedCase->aging_days }} days</div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <h6 class="fw-semibold mb-2">Delay Ownership</h6>
                                <p class="small text-muted mb-2">Current: <strong>{{ $selectedCase->delayOwner?->name ?? 'Unassigned' }}</strong></p>
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
                                <input type="text" wire:model="overrideReason" class="form-control form-control-sm mb-2" placeholder="Override reason (required)">
                                @error('overrideReason')<div class="text-danger small">{{ $message }}</div>@enderror
                                <button type="button" wire:click="saveDelayOverride" class="btn btn-sm btn-outline-secondary">Override Delay Owner</button>
                            </div>

                            <div class="mb-4">
                                <h6 class="fw-semibold mb-2">Add Note</h6>
                                <textarea wire:model="newNote" rows="2" class="form-control mb-2" placeholder="Log a call, follow-up, or note..."></textarea>
                                @error('newNote')<div class="text-danger small">{{ $message }}</div>@enderror
                                <button type="button" wire:click="addNote" class="btn btn-sm btn-outline-primary">Add Note</button>
                            </div>

                            @elseif ($drawerTab === 'documents')
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
                                                {{ $item->documentType?->name ?? 'Document' }}
                                                @if ($item->is_required)<span class="text-danger">*</span>@endif
                                            </span>
                                        </div>
                                    @endforeach
                                @else
                                    <p class="text-muted small mb-0">No checklist items. Configure payer document requirements or upload documents linked to this case.</p>
                                @endif
                            </div>

                            @elseif ($drawerTab === 'tasks')
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
                                                {{ $task->assignedAdmin?->name ?? 'Unassigned' }}
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

                            @elseif ($drawerTab === 'communication')
                            <div class="mb-4">
                                <h6 class="fw-semibold mb-2">Send Email</h6>
                                <select wire:model="emailTemplateId" class="form-select form-select-sm mb-2">
                                    <option value="">Select template...</option>
                                    @foreach ($emailTemplates as $template)
                                        <option value="{{ $template->id }}">{{ $template->name }}</option>
                                    @endforeach
                                </select>
                                <button type="button" wire:click="sendDocumentRequest" class="btn btn-sm btn-primary mb-3">
                                    <i class="ti tabler-mail me-1"></i>Send to Provider
                                </button>
                                @forelse($selectedCase->emailMessages as $msg)
                                    <div class="small border-bottom py-2">
                                        <strong>{{ $msg->subject }}</strong>
                                        <div class="text-muted">{{ $msg->created_at->format('m/d/Y g:i A') }}</div>
                                    </div>
                                @empty
                                    <p class="text-muted small">No emails linked to this case.</p>
                                @endforelse
                            </div>

                            @elseif ($drawerTab === 'billing')
                            @php $missingBilling = $billingService->missingFields($selectedCase); @endphp
                            <div class="mb-4">
                                <h6 class="fw-semibold mb-2">Billing Readiness</h6>
                                @if ($selectedCase->ready_to_bill)
                                    <span class="badge bg-success mb-2">Ready to Bill</span>
                                @elseif (count($missingBilling))
                                    <div class="alert alert-warning py-2 small">Missing: {{ implode(', ', $missingBilling) }}</div>
                                @endif
                                <div class="row g-2 small">
                                    <div class="col-6">
                                        <label class="form-label">Approval Date</label>
                                        <input type="date" wire:model="billingForm.approval_date" class="form-control form-control-sm">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label">Effective Date</label>
                                        <input type="date" wire:model="billingForm.effective_date" class="form-control form-control-sm">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label">Payer Provider ID</label>
                                        <input type="text" wire:model="billingForm.payer_provider_id" class="form-control form-control-sm">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label">Group ID</label>
                                        <input type="text" wire:model="billingForm.payer_group_id" class="form-control form-control-sm">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label">EFT Status</label>
                                        <input type="text" wire:model="billingForm.eft_status" class="form-control form-control-sm">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label">ERA Status</label>
                                        <input type="text" wire:model="billingForm.era_status" class="form-control form-control-sm">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Billing Notes</label>
                                        <textarea wire:model="billingForm.billing_notes" rows="2" class="form-control form-control-sm"></textarea>
                                    </div>
                                </div>
                                <div class="d-flex gap-2 mt-3">
                                    <button type="button" wire:click="saveBillingFields" class="btn btn-sm btn-primary">Save</button>
                                    <button type="button" wire:click="notifyBilling" class="btn btn-sm btn-outline-success">Notify Billing</button>
                                </div>
                            </div>

                            @elseif ($drawerTab === 'timeline')
                            <div class="mb-4">
                                <h6 class="fw-semibold mb-3">Activity Timeline</h6>
                                <x-admin.activity-timeline :activities="$selectedCase->activities" />
                            </div>
                            <div>
                                <h6 class="fw-semibold mb-3">Status History</h6>
                                @foreach($selectedCase->statusHistories as $history)
                                    <div class="mb-2 pb-2 border-bottom small">
                                        <span class="fw-semibold">{{ $history->status?->name ?? 'N/A' }}</span>
                                        <span class="text-muted"> · {{ $history->created_at->format('m/d/Y') }}</span>
                                        @if($history->notes)<p class="text-muted mb-0 mt-1">{{ $history->notes }}</p>@endif
                                    </div>
                                @endforeach
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endteleport
        @endif
</div>
