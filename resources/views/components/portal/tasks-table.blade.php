@props(['tasks', 'showProvider' => false, 'documentsRoute' => null])

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white">
        <h6 class="mb-0 fw-semibold">Tasks &amp; Follow-ups</h6>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    @if ($showProvider)
                        <th>Provider</th>
                    @endif
                    <th>Task</th>
                    <th>Case</th>
                    <th>Due Date</th>
                    <th>Follow-up</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($tasks as $task)
                    @php
                        $typeLabel = \App\Enums\TaskType::tryFrom($task->task_type)?->label() ?? ucfirst(str_replace('_', ' ', $task->task_type));
                        $isOverdue = $task->kanbanColumn() === 'overdue';
                    @endphp
                    <tr>
                        @if ($showProvider)
                            <td>{{ $task->provider->user->name ?? '—' }}</td>
                        @endif
                        <td>
                            <div class="fw-semibold">{{ $task->title }}</div>
                            <small class="text-muted">{{ $typeLabel }}</small>
                            @if ($documentsRoute && $task->task_type === 'document' && can_do('portal.documents.view'))
                                <div class="mt-1">
                                    <a href="{{ $documentsRoute }}" class="small">Upload document</a>
                                </div>
                            @endif
                        </td>
                        <td>
                            @if ($task->credentialingCase)
                                @if(can_do('portal.cases.view'))
                                    <a href="{{ $showProvider ? route('practice.cases') : route('provider.cases') }}" class="fw-semibold text-decoration-none">
                                        {{ $task->credentialingCase->case_number }}
                                    </a>
                                @else
                                    <span class="fw-semibold">{{ $task->credentialingCase->case_number }}</span>
                                @endif
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>{{ $task->due_date?->format('m/d/Y') ?? '—' }}</td>
                        <td>{{ $task->follow_up_date?->format('m/d/Y') ?? '—' }}</td>
                        <td>
                            @if ($isOverdue)
                                <span class="badge bg-danger">Overdue</span>
                            @else
                                <span class="badge bg-label-primary">Open</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $showProvider ? 6 : 5 }}" class="text-center text-muted py-4">No open tasks or follow-ups.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
