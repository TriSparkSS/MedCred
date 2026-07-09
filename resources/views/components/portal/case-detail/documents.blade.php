@props([
    'case',
    'checklist',
    'documentsRoute' => null,
])

<div class="d-flex justify-content-between align-items-center mb-3">
    <h6 class="fw-semibold mb-0">Document Checklist</h6>
    @if ($documentsRoute && can_do('portal.documents.upload'))
        <a href="{{ $documentsRoute }}" class="btn btn-sm btn-primary">
            <i class="ti tabler-upload me-1"></i> Upload Document
        </a>
    @endif
</div>

@if ($checklist['total'] > 0)
    <div class="mb-4">
        <div class="d-flex justify-content-between small mb-1">
            <span>{{ $checklist['received'] }} of {{ $checklist['total'] }} received</span>
            <span class="fw-semibold">{{ $checklist['percent'] }}%</span>
        </div>
        <div class="progress" style="height: 8px;">
            <div class="progress-bar bg-success" style="width: {{ $checklist['percent'] }}%"></div>
        </div>
    </div>

    <div class="list-group list-group-flush border rounded">
        @foreach ($case->documentItems as $item)
            <div class="list-group-item d-flex align-items-center gap-2">
                <i class="ti tabler-{{ $item->is_received ? 'circle-check text-success' : 'circle-dashed text-muted' }}"></i>
                <span class="{{ $item->is_received ? '' : 'text-muted' }}">
                    {{ $item->documentType->name ?? 'Document' }}
                    @if ($item->is_required)<span class="text-danger">*</span>@endif
                </span>
                @if ($item->is_received)
                    <span class="badge bg-label-success ms-auto">Received</span>
                @else
                    <span class="badge bg-label-warning ms-auto">Pending</span>
                @endif
            </div>
        @endforeach
    </div>
@else
    <div class="text-center text-muted py-5">
        <i class="ti tabler-file-off d-block mb-2 fs-3"></i>
        No checklist items for this application.
    </div>
@endif
