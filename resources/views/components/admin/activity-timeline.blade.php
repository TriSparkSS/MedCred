@props([
    'activities',
    'showCase' => false,
    'emptyMessage' => 'No activity recorded yet.',
])

@php
    $typeMeta = function (string $type): array {
        return match ($type) {
            'status_change' => ['color' => 'info', 'icon' => 'tabler-refresh', 'label' => 'Status Change'],
            'note' => ['color' => 'primary', 'icon' => 'tabler-note', 'label' => 'Note'],
            'email' => ['color' => 'warning', 'icon' => 'tabler-mail', 'label' => 'Email'],
            'call' => ['color' => 'success', 'icon' => 'tabler-phone', 'label' => 'Call'],
            'provider_upload' => ['color' => 'success', 'icon' => 'tabler-upload', 'label' => 'Provider Upload'],
            'practice_upload' => ['color' => 'success', 'icon' => 'tabler-upload', 'label' => 'Practice Upload'],
            'system' => ['color' => 'secondary', 'icon' => 'tabler-settings', 'label' => 'System'],
            default => ['color' => 'secondary', 'icon' => 'tabler-circle-dot', 'label' => ucfirst(str_replace('_', ' ', $type))],
        };
    };

    $actorName = function ($activity): string {
        if ($activity->admin) {
            return $activity->admin->name;
        }

        if ($activity->user) {
            return $activity->user->name;
        }

        return 'System';
    };
@endphp

<ul class="timeline mb-0">
    @forelse($activities as $activity)
        @php $meta = $typeMeta($activity->activity_type); @endphp
        <li class="timeline-item timeline-item-transparent pb-4" wire:key="activity-{{ $activity->id }}">
            <div class="timeline-indicator timeline-indicator-{{ $meta['color'] }}">
                <i class="icon-base ti {{ $meta['icon'] }} icon-sm"></i>
            </div>
            <div class="timeline-event">
                <div class="timeline-header mb-2">
                    <span class="badge bg-label-{{ $meta['color'] }}">{{ $meta['label'] }}</span>
                    <small class="text-muted">{{ $activity->created_at->format('m/d/Y g:i A') }}</small>
                </div>
                <p class="mb-2 fw-medium">{{ $activity->summary }}</p>
                <div class="d-flex flex-wrap align-items-center gap-3 text-muted small">
                    <span><i class="ti tabler-user me-1"></i>{{ $actorName($activity) }}</span>
                    @if ($showCase && $activity->credentialingCase)
                        <span>
                            <i class="ti tabler-briefcase me-1"></i>{{ $activity->credentialingCase->case_number }}
                        </span>
                    @endif
                    @if ($activity->reference_number)
                        <span><i class="ti tabler-hash me-1"></i>{{ $activity->reference_number }}</span>
                    @endif
                </div>
            </div>
        </li>
    @empty
        <li class="list-unstyled">
            <div class="text-center py-5 text-muted">
                <i class="ti tabler-timeline-event d-block mb-2" style="font-size: 2rem;"></i>
                {{ $emptyMessage }}
            </div>
        </li>
    @endforelse
</ul>
