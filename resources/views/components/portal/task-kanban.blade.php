@props([
    'groupedTasks',
    'caseShowRoute' => 'provider.cases.show',
    'documentsRoute' => null,
    'showProvider' => false,
])

<div class="row g-3">
    @foreach ($groupedTasks as $columnKey => $column)
        <div class="col-lg-4 col-md-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white border-bottom py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold">{{ $column['label'] }}</h6>
                        <span class="badge bg-label-{{ $column['color'] }}">{{ $column['tasks']->count() }}</span>
                    </div>
                </div>
                <div class="card-body p-3" style="min-height: 160px; max-height: 420px; overflow-y: auto;">
                    @forelse($column['tasks'] as $task)
                        <x-portal.task-card
                            :task="$task"
                            :case-show-route="$caseShowRoute"
                            :documents-route="$documentsRoute"
                            :show-provider="$showProvider"
                        />
                    @empty
                        <p class="text-muted small text-center py-4 mb-0">No tasks</p>
                    @endforelse
                </div>
            </div>
        </div>
    @endforeach
</div>
