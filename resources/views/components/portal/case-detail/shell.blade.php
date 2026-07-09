@props([
    'case',
    'checklist',
    'groupedTasks',
    'activeTab',
    'casesRoute',
    'documentsRoute',
    'caseShowRoute' => 'provider.cases.show',
    'showProvider' => false,
    'headerComponent' => 'provider.page-header',
])

<div>
    <div class="container-fluid flex-grow-1 px-3 px-md-4 py-3 py-md-4">
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="{{ $casesRoute }}">My Applications</a></li>
                <li class="breadcrumb-item active" aria-current="page">{{ $case->case_number }}</li>
            </ol>
        </nav>

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                    <div>
                        <h4 class="fw-bold mb-1">{{ $case->case_number }}</h4>
                        <div class="text-muted">{{ $case->payer->name ?? '—' }}</div>
                        <span class="badge bg-label-primary mt-2">{{ $case->status->name ?? '—' }}</span>
                    </div>
                    <a href="{{ $casesRoute }}" class="btn btn-outline-secondary btn-sm">
                        <i class="ti tabler-arrow-left me-1"></i> Back to list
                    </a>
                </div>
                @if ($checklist['total'] > 0)
                    <div class="mt-3">
                        <div class="d-flex justify-content-between small mb-1">
                            <span>Checklist progress</span>
                            <span class="fw-semibold">{{ $checklist['received'] }}/{{ $checklist['total'] }} ({{ $checklist['percent'] }}%)</span>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-primary" style="width: {{ $checklist['percent'] }}%"></div>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <ul class="nav nav-pills nav-fill mb-4">
            @foreach (['overview' => 'Overview', 'documents' => 'Documents', 'tasks' => 'My Tasks', 'timeline' => 'Timeline'] as $key => $label)
                <li class="nav-item">
                    <button type="button"
                        class="nav-link {{ $activeTab === $key ? 'active' : '' }}"
                        wire:click="setTab('{{ $key }}')">
                        {{ $label }}
                    </button>
                </li>
            @endforeach
        </ul>

        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                @if ($activeTab === 'overview')
                    <x-portal.case-detail.overview :case="$case" :checklist="$checklist" :show-provider="$showProvider" />
                @elseif ($activeTab === 'documents')
                    <x-portal.case-detail.documents :case="$case" :checklist="$checklist" :documents-route="$documentsRoute" />
                @elseif ($activeTab === 'tasks')
                    @php $hasTasks = collect($groupedTasks)->sum(fn ($col) => $col['tasks']->count()); @endphp
                    @if ($hasTasks > 0)
                        <x-portal.task-kanban
                            :grouped-tasks="$groupedTasks"
                            :case-show-route="$caseShowRoute"
                            :documents-route="$documentsRoute"
                            :show-provider="$showProvider"
                        />
                    @else
                        <div class="text-center text-muted py-5">
                            <i class="ti tabler-checkbox d-block mb-2 fs-3"></i>
                            No open tasks for this application.
                        </div>
                    @endif
                @elseif ($activeTab === 'timeline')
                    <x-portal.case-detail.timeline :case="$case" />
                @endif
            </div>
        </div>
    </div>
</div>
