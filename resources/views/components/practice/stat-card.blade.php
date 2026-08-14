@props([
    'label',
    'value',
    'hint' => null,
    'valueClass' => '',
    'badge' => null,
])

<div class="card shadow-sm border-0 h-100">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start mb-2">
            <p class="text-uppercase text-muted small mb-0 fw-semibold">{{ $label }}</p>
            @if ($badge)
                <span class="badge bg-label-primary">{{ $badge }}</span>
            @endif
        </div>
        <h3 class="fw-bold mb-0 {{ $valueClass }}">{{ $value }}</h3>
        @if ($hint)
            <p class="mb-0 text-muted small mt-1">{{ $hint }}</p>
        @endif
    </div>
</div>
