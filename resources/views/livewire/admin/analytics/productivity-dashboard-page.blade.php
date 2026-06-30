<div class="container-fluid flex-grow-1 px-3 px-md-4 py-3 py-md-4">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <h3 class="fw-bold text-primary mb-1">Productivity Analytics</h3>
            <p class="text-muted mb-0">Executive workload, payer turnaround, and upcoming recredentialing.</p>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white"><h6 class="fw-semibold mb-0">Executive Performance</h6></div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead class="table-light"><tr><th>Executive</th><th>Active</th><th>Approved</th><th>Overdue</th><th>Open Tasks</th><th>Avg Turnaround</th></tr></thead>
                <tbody>
                    @foreach($executives as $row)
                        <tr>
                            <td class="fw-medium">{{ $row['name'] }}</td>
                            <td>{{ $row['active_cases'] }}</td>
                            <td>{{ $row['approved_cases'] }}</td>
                            <td>{{ $row['overdue_cases'] }}</td>
                            <td>{{ $row['open_tasks'] }}</td>
                            <td>{{ $row['avg_turnaround_days'] ? $row['avg_turnaround_days'] . ' days' : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white"><h6 class="fw-semibold mb-0">Turnaround by Payer</h6></div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead class="table-light"><tr><th>Payer</th><th>Approved</th><th>Avg Days</th></tr></thead>
                        <tbody>
                            @forelse($payerTurnaround as $row)
                                <tr><td>{{ $row['payer'] }}</td><td>{{ $row['approved_count'] }}</td><td>{{ $row['avg_days'] }}</td></tr>
                            @empty
                                <tr><td colspan="3" class="text-muted text-center py-3">No approved cases with dates yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white"><h6 class="fw-semibold mb-0">Recredentialing (90 days)</h6></div>
                <ul class="list-group list-group-flush">
                    @forelse($recredentialing as $case)
                        <li class="list-group-item">
                            <div class="fw-medium">{{ $case->provider->user->name ?? '' }} — {{ $case->payer->name ?? '' }}</div>
                            <small class="text-muted">Due {{ $case->revalidation_due_date?->format('m/d/Y') }}</small>
                        </li>
                    @empty
                        <li class="list-group-item text-muted">No recredentialing due in the next 90 days.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</div>
