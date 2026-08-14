@props([
    'case',
    'checklist',
    'showProvider' => false,
])

<div class="row g-3">
    @if ($showProvider)
        <div class="col-md-6">
            <div class="border rounded p-3 h-100">
                <div class="text-muted small mb-1">Provider</div>
                <div class="fw-semibold">{{ $case->provider->user->name ?? '—' }}</div>
            </div>
        </div>
    @endif
    <div class="col-md-6">
        <div class="border rounded p-3 h-100">
            <div class="text-muted small mb-1">Practice</div>
            <div class="fw-semibold">{{ $case->practice->legal_name ?? '—' }}</div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="border rounded p-3 h-100">
            <div class="text-muted small mb-1">Payer</div>
            <div class="fw-semibold">{{ $case->payer->name ?? '—' }}</div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="border rounded p-3 h-100">
            <div class="text-muted small mb-1">Case Type</div>
            <div class="fw-semibold">{{ $case->caseType->name ?? '—' }}</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="border rounded p-3 h-100">
            <div class="text-muted small mb-1">State</div>
            <div class="fw-semibold">{{ $case->state ?: '—' }}</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="border rounded p-3 h-100">
            <div class="text-muted small mb-1">Location</div>
            <div class="fw-semibold">{{ $case->location->name ?? '—' }}</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="border rounded p-3 h-100">
            <div class="text-muted small mb-1">Aging</div>
            <div class="fw-semibold">{{ $case->aging_days }} days</div>
        </div>
    </div>
</div>

<h6 class="fw-semibold mt-4 mb-3">Key Dates</h6>
<div class="row g-3">
    @foreach ([
        'Intake Date' => $case->intake_date,
        'Submission Date' => $case->submission_date,
        'Approval Date' => $case->approval_date,
        'Effective Date' => $case->effective_date,
        'Next Follow-up' => $case->next_follow_up_date,
    ] as $label => $date)
        <div class="col-md-4 col-sm-6">
            <div class="border rounded p-3">
                <div class="text-muted small mb-1">{{ $label }}</div>
                <div class="fw-semibold">{{ $date?->format('m/d/Y') ?? '—' }}</div>
            </div>
        </div>
    @endforeach
</div>

<h6 class="fw-semibold mt-4 mb-3">Document Checklist Summary</h6>
@if ($checklist['total'] > 0)
    <div class="d-flex justify-content-between small mb-2">
        <span>{{ $checklist['received'] }} of {{ $checklist['total'] }} received</span>
        <span class="fw-semibold">{{ $checklist['percent'] }}%</span>
    </div>
    <div class="progress mb-0" style="height: 8px;">
        <div class="progress-bar bg-success" style="width: {{ $checklist['percent'] }}%"></div>
    </div>
@else
    <p class="text-muted mb-0">No checklist items configured for this application yet.</p>
@endif
