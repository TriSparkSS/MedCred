@props([
    'title',
    'subtitle' => null,
])

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-start gap-3">
        <div>
            <h3 class="fw-bold text-primary mb-2">{{ $title }}</h3>
            @if ($subtitle)
                <p class="text-muted mb-0">{{ $subtitle }}</p>
            @endif
        </div>
        @if (isset($actions))
            <div class="d-flex flex-column flex-sm-row gap-2">{{ $actions }}</div>
        @endif
    </div>
</div>
