<div class="container-fluid flex-grow-1 px-3 px-md-4 py-3 py-md-4">

    <div class="row g-4 mb-3 align-items-center">
        <div class="col-md-8">
            <h4 class="fw-bold mb-1"><i class="ti tabler-layout-kanban me-2 text-primary"></i>Tasks & Follow-ups</h4>
            <p class="text-muted mb-0">Track credentialing tasks by due date and assignment.</p>
        </div>
        <div class="col-md-4 text-md-end">
            <button wire:click="openCreateModal" class="btn btn-primary btn-sm">
                <i class="ti tabler-plus me-1"></i>New Task
            </button>
        </div>
    </div>

    <div class="row g-2 align-items-center mb-4">
        <div class="col-auto">
            <button wire:click="$toggle('filterMyTasks')"
                class="btn btn-sm px-3 {{ $filterMyTasks ? 'btn-secondary' : 'btn-outline-secondary' }}">
                <i class="ti tabler-checks me-1"></i>My Tasks
            </button>
        </div>
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
                    <option value="{{ $provider->id }}">{{ $provider->user->name ?? 'Provider #' . $provider->id }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select wire:model.live="filterTaskType" class="form-select form-select-sm">
                <option value="">All Types</option>
                <option value="document">Document Request</option>
                <option value="follow_up">Follow-up</option>
                <option value="sla">Payer Follow-up</option>
                <option value="escalation">Escalation</option>
                <option value="expiry">Expiry</option>
                <option value="manual">Manual</option>
            </select>
        </div>
        <div class="col-md-2">
            <input type="search" wire:model.live.debounce.300ms="filterCaseSearch" class="form-control form-control-sm"
                placeholder="Search tasks or case #...">
        </div>
        <div class="col text-end text-muted small">
            {{ $openCount }} open task{{ $openCount !== 1 ? 's' : '' }}
        </div>
    </div>

    <div class="row g-3">
        @foreach ($board as $columnKey => $column)
            <div class="col-lg-3 col-md-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white border-bottom py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 fw-bold">{{ $column['meta']['label'] }}</h6>
                            <span class="badge bg-label-{{ $column['meta']['color'] }}">{{ $column['tasks']->count() }}</span>
                        </div>
                    </div>
                    <div class="card-body p-3" style="min-height: 200px; max-height: 70vh; overflow-y: auto;">
                        @forelse($column['tasks'] as $task)
                            <div class="border rounded-3 p-3 mb-3 bg-white shadow-sm" wire:key="task-{{ $task->id }}">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    @if ($task->priority)
                                        <span class="badge bg-label-primary">{{ $task->priority->name }}</span>
                                    @else
                                        <span class="badge bg-label-secondary">{{ $taskTypeLabels($task->task_type) }}</span>
                                    @endif
                                    <span class="rounded-circle d-inline-block bg-{{ $column['meta']['color'] }}"
                                        style="width:8px; height:8px;"></span>
                                </div>
                                <div class="fw-semibold mb-1">{{ $task->title }}</div>
                                @if ($task->description)
                                    <p class="small text-muted mb-2">{{ Str::limit($task->description, 80) }}</p>
                                @endif
                                <div class="small text-muted mb-2">
                                    @if ($task->provider)
                                        <div><i class="ti tabler-user me-1"></i>{{ $task->provider->user->name ?? 'Provider' }}</div>
                                    @endif
                                    @if ($task->credentialingCase)
                                        <div>
                                            <i class="ti tabler-briefcase me-1"></i>
                                            <a href="{{ route('admin.tasks.kanban', ['case_id' => $task->credentialing_case_id]) }}"
                                                class="text-decoration-none">{{ $task->credentialingCase->case_number }}</a>
                                        </div>
                                    @endif
                                </div>
                                <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
                                    <small class="text-muted">
                                        <i class="ti tabler-calendar me-1"></i>
                                        {{ $task->due_date?->format('m/d/Y') ?: 'No due date' }}
                                    </small>
                                    <select wire:change="reassignTask({{ $task->id }}, $event.target.value)"
                                        class="form-select form-select-sm" style="width: auto; max-width: 120px;">
                                        <option value="">Unassigned</option>
                                        @foreach ($admins as $admin)
                                            <option value="{{ $admin->id }}" @selected($task->assigned_admin_id == $admin->id)>
                                                {{ Str::limit($admin->name, 12) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="d-flex gap-1 mt-2 pt-2 border-top">
                                    @if ($columnKey === 'completed')
                                        <button wire:click="reopenTask({{ $task->id }})" class="btn btn-sm btn-outline-secondary flex-grow-1">
                                            Reopen
                                        </button>
                                    @else
                                        <button wire:click="completeTask({{ $task->id }})" class="btn btn-sm btn-outline-success flex-grow-1">
                                            Complete
                                        </button>
                                    @endif
                                    <button wire:click="deleteTask({{ $task->id }})" wire:confirm="Delete this task?"
                                        class="btn btn-sm btn-outline-danger">
                                        <i class="ti tabler-trash"></i>
                                    </button>
                                </div>
                            </div>
                        @empty
                            <p class="text-muted small text-center py-4 mb-0">No tasks</p>
                        @endforelse
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    @if ($showModal)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,.5);">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">New Task</h5>
                        <button type="button" class="btn-close" wire:click="$set('showModal', false)"></button>
                    </div>
                    <form wire:submit.prevent="saveTask">
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">Title <span class="text-danger">*</span></label>
                                    <input type="text" wire:model="formData.title" class="form-control @error('formData.title') is-invalid @enderror">
                                    @error('formData.title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Description</label>
                                    <textarea wire:model="formData.description" rows="2" class="form-control"></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Assign To</label>
                                    <select wire:model="formData.assigned_admin_id" class="form-select">
                                        <option value="">Unassigned</option>
                                        @foreach ($admins as $admin)
                                            <option value="{{ $admin->id }}">{{ $admin->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Due Date</label>
                                    <input type="date" wire:model="formData.due_date" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Provider</label>
                                    <select wire:model="formData.provider_id" class="form-select">
                                        <option value="">None</option>
                                        @foreach ($providers as $provider)
                                            <option value="{{ $provider->id }}">{{ $provider->user->name ?? 'Provider #' . $provider->id }}</option>
                                        @endforeach
                                    </select>
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
                                    <select wire:model="formData.task_type" class="form-select">
                                        <option value="manual">Manual</option>
                                        <option value="follow_up">Follow-up</option>
                                        <option value="document">Document</option>
                                        <option value="expiry">Expiry</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" wire:click="$set('showModal', false)">Cancel</button>
                            <button type="submit" class="btn btn-primary">Create Task</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
