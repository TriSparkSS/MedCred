<div class="container-fluid flex-grow-1 px-3 px-md-4 py-3 py-md-4">
    <div class="row g-4 mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-start gap-3">
                    <div>
                        <h3 class="fw-bold text-primary mb-2">Provider Practice Assignment</h3>
                        <p class="text-muted mb-0">Assign providers to practices and manage primary affiliations.</p>
                    </div>
                    <a href="{{ route('admin.practices') }}" class="btn btn-outline-secondary">
                        <i class="ti tabler-building me-1"></i> Practices
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h5 class="mb-1">{{ $assignmentId ? 'Edit Assignment' : 'Assign Provider' }}</h5>
                            <p class="text-muted mb-0">Connect one provider with one practice.</p>
                        </div>
                        @if ($assignmentId)
                            <button type="button" wire:click="resetForm" class="btn btn-sm btn-outline-secondary">
                                <i class="ti tabler-x me-1"></i> Clear
                            </button>
                        @endif
                    </div>

                    <form wire:submit.prevent="save">
                        <div class="mb-3">
                            <label class="form-label">Provider</label>
                            <select wire:model="formData.provider_id" class="form-select">
                                <option value="">Select provider...</option>
                                @foreach ($providers as $provider)
                                    <option value="{{ $provider->id }}">
                                        {{ $provider->user->name ?? 'Provider #' . $provider->id }}{{ $provider->npi ? ' - ' . $provider->npi : '' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('formData.provider_id') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Practice</label>
                            <select wire:model="formData.practice_id" class="form-select">
                                <option value="">Select practice...</option>
                                @foreach ($practices as $practice)
                                    <option value="{{ $practice->id }}">
                                        {{ $practice->legal_name }}{{ $practice->group_npi ? ' - ' . $practice->group_npi : '' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('formData.practice_id') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Start Date</label>
                                <input type="date" wire:model="formData.start_date" class="form-control">
                                @error('formData.start_date') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">End Date</label>
                                <input type="date" wire:model="formData.end_date" class="form-control">
                                @error('formData.end_date') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="form-check form-switch mt-3">
                            <input class="form-check-input" type="checkbox" wire:model="formData.primary_flag" id="primaryFlag">
                            <label class="form-check-label" for="primaryFlag">Primary Practice</label>
                            @error('formData.primary_flag') <span class="text-danger d-block">{{ $message }}</span> @enderror
                        </div>

                        <div class="mt-4">
                            <button class="btn btn-primary">
                                {{ $assignmentId ? 'Save Assignment' : 'Assign Provider' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="ti tabler-search"></i></span>
                        <input type="search" wire:model.live="search" class="form-control border-start-0"
                            placeholder="Search by provider, NPI, practice, group NPI...">
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-body pb-0">
                    <h5 class="mb-1">Assignments</h5>
                    <p class="text-muted mb-4">{{ $assignments->total() }} total assignments</p>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Provider</th>
                                <th>Practice</th>
                                <th>Primary</th>
                                <th>Dates</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($assignments as $assignment)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $assignment->provider->user->name ?? 'N/A' }}</div>
                                        <small class="text-muted">{{ $assignment->provider->specialty->name ?? 'No Specialty' }}</small>
                                    </td>
                                    <td>
                                        <div class="fw-semibold">{{ $assignment->practice->legal_name ?? 'N/A' }}</div>
                                        <small class="text-muted">{{ $assignment->practice->group_npi ?? 'No Group NPI' }}</small>
                                    </td>
                                    <td>
                                        <span class="badge bg-label-{{ $assignment->primary_flag ? 'success' : 'secondary' }}">
                                            {{ $assignment->primary_flag ? 'Primary' : 'Secondary' }}
                                        </span>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            {{ $assignment->start_date?->format('m/d/Y') ?? 'N/A' }}
                                            -
                                            {{ $assignment->end_date?->format('m/d/Y') ?? 'Present' }}
                                        </small>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group" role="group">
                                            <button wire:click="edit({{ $assignment->id }})" class="btn btn-sm btn-outline-primary" title="Edit">
                                                <i class="ti tabler-edit"></i>
                                            </button>
                                            <button wire:click="delete({{ $assignment->id }})" class="btn btn-sm btn-outline-danger" title="Delete">
                                                <i class="ti tabler-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-5">
                                        <i class="ti tabler-link-off d-block mb-2" style="font-size: 2rem; color: #ccc;"></i>
                                        <p class="text-muted mb-0">No assignments found</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-body border-top">
                    {{ $assignments->links('livewire::bootstrap') }}
                </div>
            </div>
        </div>
    </div>
</div>
