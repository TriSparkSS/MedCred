@props([
    'items',
    'filter',
    'counts',
    'emptyOpen' => 'No open items assigned to you.',
    'emptyClosed' => 'No closed items yet.',
])

<div class="card shadow-sm border-0">
    <div class="card-header bg-white d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3">
        <div>
            <h6 class="mb-1 fw-semibold">Assigned to me</h6>
            <small class="text-muted">Applications, document requests, and tasks.</small>
        </div>
        <div class="btn-group">
            <button type="button" wire:click="setAssignmentFilter('open')"
                class="btn btn-sm {{ $filter === 'open' ? 'btn-primary' : 'btn-outline-secondary' }}">
                Open <span class="badge bg-label-{{ $filter === 'open' ? 'light' : 'primary' }} ms-1">{{ $counts['open'] }}</span>
            </button>
            <button type="button" wire:click="setAssignmentFilter('closed')"
                class="btn btn-sm {{ $filter === 'closed' ? 'btn-primary' : 'btn-outline-secondary' }}">
                Closed <span class="badge bg-label-{{ $filter === 'closed' ? 'light' : 'secondary' }} ms-1">{{ $counts['closed'] }}</span>
            </button>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Type</th>
                    <th>Item</th>
                    <th>Status</th>
                    <th>Due</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $item)
                    <tr wire:key="assigned-{{ $item['id'] }}">
                        <td><span class="badge bg-label-secondary">{{ $item['type_label'] }}</span></td>
                        <td>
                            <div class="fw-semibold">{{ $item['title'] }}</div>
                            @if (! empty($item['subtitle']))
                                <small class="text-muted">{{ $item['subtitle'] }}</small>
                            @endif
                        </td>
                        <td>
                            <span class="badge bg-label-{{ $item['status_class'] }}">{{ $item['status'] }}</span>
                        </td>
                        <td>
                            <small class="text-muted">{{ $item['due_date']?->format('m/d/Y') ?: '—' }}</small>
                        </td>
                        <td class="text-end">
                            <a href="{{ $item['url'] }}" class="btn btn-sm btn-primary">Open</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-5">
                            {{ $filter === 'open' ? $emptyOpen : $emptyClosed }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
