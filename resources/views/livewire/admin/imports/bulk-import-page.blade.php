<div class="container-fluid flex-grow-1 px-3 px-md-4 py-3 py-md-4">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <h3 class="fw-bold text-primary mb-1">Bulk CSV Import</h3>
            <p class="text-muted mb-0">Import providers from CSV. Required columns: <code>name</code>, <code>email</code>. Optional: <code>npi</code>, <code>phone</code>, <code>specialty</code>, <code>password</code>.</p>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <form wire:submit.prevent="importProviders" class="row g-3 align-items-end">
                <div class="col-md-8">
                    <label class="form-label">Provider CSV File</label>
                    <input type="file" wire:model="importFile" class="form-control @error('importFile') is-invalid @enderror" accept=".csv,.txt">
                    @error('importFile')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary w-100" wire:loading.attr="disabled">Import Providers</button>
                </div>
            </form>
            @if($lastBatch)
                <div class="alert alert-info mt-3 mb-0">
                    Last import: {{ $lastBatch->success_rows }}/{{ $lastBatch->total_rows }} rows succeeded.
                    @if($lastBatch->errors)
                        <ul class="mb-0 mt-2 small">@foreach(array_slice($lastBatch->errors, 0, 5) as $err)<li>{{ $err }}</li>@endforeach</ul>
                    @endif
                </div>
            @endif
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white"><h6 class="mb-0">Recent Imports</h6></div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead class="table-light"><tr><th>File</th><th>Type</th><th>Success</th><th>Failed</th><th>Date</th></tr></thead>
                <tbody>
                    @forelse($batches as $batch)
                        <tr>
                            <td>{{ $batch->filename }}</td>
                            <td>{{ $batch->type }}</td>
                            <td>{{ $batch->success_rows }}</td>
                            <td>{{ $batch->failed_rows }}</td>
                            <td>{{ $batch->created_at->format('m/d/Y g:i A') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-3">No imports yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
