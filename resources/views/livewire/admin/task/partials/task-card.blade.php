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
    <div class="fw-semibold mb-1">
        <button type="button" wire:click="openDetail({{ $task->id }})" class="btn btn-link p-0 text-start text-body fw-semibold">
            {{ $task->title }}
        </button>
    </div>
    @if ($task->description)
        <p class="small text-muted mb-2">{{ Str::limit($task->description, 80) }}</p>
    @endif
    <div class="small text-muted mb-2">
        @if ($task->provider)
            <div><i class="ti tabler-user me-1"></i>{{ $task->provider->user->name ?? 'Provider' }}</div>
        @endif
        @if ($task->credentialingCase)
            <div><i class="ti tabler-briefcase me-1"></i>{{ $task->credentialingCase->case_number }}</div>
        @endif
    </div>
    <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
        <small class="text-muted">
            <i class="ti tabler-calendar me-1"></i>
            {{ $task->due_date?->format('m/d/Y') ?: 'No due date' }}
        </small>
        @can('assign', $task)
            <select wire:change="assignTask({{ $task->id }}, $event.target.value)"
                class="form-select form-select-sm" style="width: auto; max-width: 120px;">
                <option value="">Unassigned</option>
                @foreach ($admins as $admin)
                    <option value="{{ $admin->id }}" @selected($task->assigned_admin_id == $admin->id)>
                        {{ Str::limit($admin->displayLabel(), 20) }}
                    </option>
                @endforeach
            </select>
        @else
            <small>{{ $task->assignedAdmin->name ?? 'Unassigned' }}</small>
        @endcan
    </div>
    <div class="d-flex gap-1 mt-2 pt-2 border-top flex-wrap">
        @if ($columnKey === 'completed')
            @can('reopen', $task)
                <button wire:click="reopenTask({{ $task->id }})" class="btn btn-sm btn-outline-secondary flex-grow-1">Reopen</button>
            @endcan
        @else
            @can('update', $task)
                <button wire:click="completeTask({{ $task->id }})" class="btn btn-sm btn-outline-success flex-grow-1">Complete</button>
            @endcan
            @can('escalate', $task)
                <button wire:click="escalateTask({{ $task->id }})" class="btn btn-sm btn-outline-danger">Escalate</button>
            @endcan
        @endif
        @can('update', $task)
            <button wire:click="openEditModal({{ $task->id }})" class="btn btn-sm btn-outline-primary"><i class="ti tabler-edit"></i></button>
        @endcan
        @can('delete', $task)
            <button wire:click="deleteTask({{ $task->id }})" wire:confirm="Delete this task?"
                class="btn btn-sm btn-outline-danger"><i class="ti tabler-trash"></i></button>
        @endcan
    </div>
</div>
