@props(['case'])

@if ($case->statusHistories->isNotEmpty())
    <div class="list-group list-group-flush border rounded">
        @foreach ($case->statusHistories->sortByDesc('created_at') as $history)
            <div class="list-group-item">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="fw-semibold">{{ $history->status->name ?? 'Status updated' }}</div>
                    </div>
                    <small class="text-muted text-nowrap ms-3">
                        {{ $history->created_at?->format('m/d/Y g:i A') }}
                    </small>
                </div>
            </div>
        @endforeach
    </div>
@else
    <div class="text-center text-muted py-5">
        <i class="ti tabler-timeline d-block mb-2 fs-3"></i>
        No status history recorded yet.
    </div>
@endif
