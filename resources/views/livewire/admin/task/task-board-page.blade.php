<div class="container-fluid flex-grow-1 px-3 px-md-4 py-3 py-md-4">

    <div class="row g-4 mb-3 align-items-center">
        <div class="col-md-6">
            <h4 class="fw-bold mb-1"><i class="ti tabler-layout-kanban me-2 text-primary"></i>Tasks & Follow-ups</h4>
            <p class="text-muted mb-0">Track credentialing tasks by due date and assignment.</p>
        </div>
        <div class="col-md-6 text-md-end d-flex gap-2 justify-content-md-end align-items-center">
            <div class="btn-group btn-group-sm">
                <button wire:click="setViewMode('board')"
                    class="btn {{ $viewMode === 'board' ? 'btn-primary' : 'btn-outline-primary' }}">
                    <i class="ti tabler-layout-kanban me-1"></i>Board
                </button>
                <button wire:click="setViewMode('list')"
                    class="btn {{ $viewMode === 'list' ? 'btn-primary' : 'btn-outline-primary' }}">
                    <i class="ti tabler-list me-1"></i>List
                </button>
            </div>
            @can('create', \App\Models\Task::class)
                <button wire:click="openCreateModal" class="btn btn-primary btn-sm">
                    <i class="ti tabler-plus me-1"></i>New Task
                </button>
            @endcan
        </div>
    </div>

    <div class="row g-2 align-items-center mb-3">
        <div class="col-auto">
            <button wire:click="$toggle('filterMyTasks')"
                class="btn btn-sm px-3 {{ $filterMyTasks ? 'btn-secondary' : 'btn-outline-secondary' }}">
                <i class="ti tabler-checks me-1"></i>My Tasks
            </button>
        </div>
        @foreach (['overdue' => 'Overdue', 'due_today' => 'Due Today', 'upcoming' => 'Upcoming', 'escalated' => 'Escalated', 'completed' => 'Completed'] as $chipKey => $chipLabel)
            <div class="col-auto">
                <button wire:click="setChip('{{ $chipKey }}')"
                    class="btn btn-sm {{ $filterChip === $chipKey ? 'btn-primary' : 'btn-outline-secondary' }}">
                    {{ $chipLabel }}
                </button>
            </div>
        @endforeach
    </div>

    <div class="row g-2 align-items-center mb-4">
        <div class="col-md-2">
            <select wire:model.live="filterAssignee" class="form-select form-select-sm">
                <option value="">All Assignees</option>
                @foreach ($admins as $admin)
                    <option value="{{ $admin->id }}">{{ $admin->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select wire:model.live="filterCaseId" class="form-select form-select-sm">
                <option value="">All Cases</option>
                @foreach ($cases as $case)
                    <option value="{{ $case->id }}">{{ $case->case_number }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select wire:model.live="filterProviderId" class="form-select form-select-sm">
                <option value="">All Providers</option>
                @foreach ($providers as $provider)
                    <option value="{{ $provider->id }}">{{ $provider->user->name ?? 'Provider #' . $provider->id }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select wire:model.live="filterPayerId" class="form-select form-select-sm">
                <option value="">All Payers</option>
                @foreach ($payers as $payer)
                    <option value="{{ $payer->id }}">{{ $payer->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select wire:model.live="filterTaskType" class="form-select form-select-sm">
                <option value="">All Types</option>
                <option value="manual">Manual</option>
                <option value="provider_follow_up">Provider Follow-up</option>
                <option value="payer_follow_up">Payer Follow-up</option>
                <option value="document">Document</option>
                <option value="escalation">Escalation</option>
                <option value="review">Review</option>
                <option value="renewal">Renewal</option>
                <option value="billing_readiness">Billing Readiness</option>
                <option value="expiry">Expiry</option>
                <option value="sla">SLA</option>
            </select>
        </div>
        <div class="col-md-2">
            <select wire:model.live="filterStatus" class="form-select form-select-sm">
                <option value="">All Statuses</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}">{{ $status->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <input type="search" wire:model.live.debounce.300ms="filterSearch" class="form-control form-control-sm"
                placeholder="Search tasks or case #...">
        </div>
        <div class="col text-end text-muted small">
            {{ $openCount }} open task{{ $openCount !== 1 ? 's' : '' }}
        </div>
    </div>

    @if ($viewMode === 'board')
        <div class="row g-3">
            @foreach ($board as $columnKey => $column)
                <div class="col-lg-3 col-md-6">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-white border-bottom py-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="mb-0 fw-bold">{{ $column['meta']['label'] }}</h6>
                                <span
                                    class="badge bg-label-{{ $column['meta']['color'] }}">{{ $column['tasks']->count() }}</span>
                            </div>
                        </div>
                        <div class="card-body p-3" style="min-height: 200px; max-height: 70vh; overflow-y: auto;">
                            @forelse($column['tasks'] as $task)
                                @include('livewire.admin.task.partials.task-card', [
                                    'task' => $task,
                                    'columnKey' => $columnKey,
                                    'column' => $column,
                                ])
                            @empty
                                <p class="text-muted small text-center py-4 mb-0">No tasks</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="card shadow-sm border-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Task</th>
                            <th>Assigned To</th>
                            <th>Status</th>
                            <th>Priority</th>
                            <th>Due Date</th>
                            <th>Follow-up</th>
                            <th>Case</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($listTasks as $task)
                            <tr wire:key="list-task-{{ $task->id }}">
                                <td>
                                    <div class="fw-semibold">{{ $task->title }}</div>
                                    <small class="text-muted">{{ $taskTypeLabels($task->task_type) }}</small>
                                </td>
                                <td><small>{{ $task->assignedAdmin->name ?? 'Unassigned' }}</small></td>
                                <td><span
                                        class="badge bg-label-secondary">{{ $task->status?->label() ?? 'Open' }}</span>
                                </td>
                                <td><small>{{ $task->priority->name ?? '—' }}</small></td>
                                <td><small>{{ $task->due_date?->format('m/d/Y') ?: '—' }}</small></td>
                                <td><small>{{ $task->follow_up_date?->format('m/d/Y') ?: '—' }}</small></td>
                                <td><small>{{ $task->credentialingCase->case_number ?? '—' }}</small></td>
                                <td class="text-end">
                                    <button wire:click="openDetail({{ $task->id }})"
                                        class="btn btn-sm btn-outline-primary">View</button>
                                    @can('update', $task)
                                        <button wire:click="openEditModal({{ $task->id }})"
                                            class="btn btn-sm btn-outline-secondary">Edit</button>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">No tasks found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($listTasks)
                <div class="card-footer">{{ $listTasks->links('livewire::bootstrap') }}</div>
            @endif
        </div>
    @endif

    @include('livewire.admin.task.partials.task-form-modals')

    <livewire:admin.task.task-detail-drawer />
</div>
