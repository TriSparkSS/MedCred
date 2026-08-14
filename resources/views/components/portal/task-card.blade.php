@props([
    'task',
    'caseShowRoute' => 'provider.cases.show',
    'documentsRoute' => null,
    'showProvider' => false,
])

@php
    $typeLabel = \App\Enums\TaskType::tryFrom($task->task_type)?->label() ?? ucfirst(str_replace('_', ' ', $task->task_type));
    $column = $task->kanbanColumn();
    if ($column === 'escalated') {
        $column = 'overdue';
    }
    $badgeClass = match ($column) {
        'overdue' => 'bg-danger',
        'due_today' => 'bg-warning',
        default => 'bg-label-primary',
    };
@endphp

<div class="card border shadow-none mb-2">
    <div class="card-body p-3">
        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
            <div class="fw-semibold small">{{ $task->title }}</div>
            <span class="badge {{ $badgeClass }}">{{ $typeLabel }}</span>
        </div>
        @if ($showProvider && $task->relationLoaded('provider'))
            <div class="text-muted small mb-1">{{ $task->provider->user->name ?? '—' }}</div>
        @endif
        @if ($task->credentialing_case_id && can_do('portal.cases.view'))
            <div class="small mb-1">
                <a href="{{ route($caseShowRoute, $task->credentialing_case_id) }}" class="text-decoration-none">
                    {{ $task->credentialingCase->case_number ?? 'View case' }}
                </a>
            </div>
        @endif
        <div class="text-muted small">
            @if ($task->due_date)
                <div><i class="ti tabler-calendar me-1"></i> Due {{ $task->due_date->format('m/d/Y') }}</div>
            @endif
            @if ($task->follow_up_date)
                <div><i class="ti tabler-bell me-1"></i> Follow-up {{ $task->follow_up_date->format('m/d/Y') }}</div>
            @endif
        </div>
        @if ($task->task_type === 'document' && $documentsRoute && can_do('portal.documents.view'))
            <a href="{{ $documentsRoute }}" class="small d-inline-block mt-2">Upload document</a>
        @endif
    </div>
</div>
