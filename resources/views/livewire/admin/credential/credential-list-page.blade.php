<div class="container-fluid flex-grow-1 px-3 px-md-4 py-3 py-md-4">
        {{-- Header --}}
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-start gap-3">
                <div>
                    <h3 class="fw-bold text-primary mb-1">Credentialing Tracker</h3>
                    <p class="text-muted mb-0">Track payer enrollment applications, status, and follow-ups.</p>
                </div>
            </div>
        </div>

        {{-- KPI Stats --}}
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-lg-3">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <p class="text-uppercase text-muted small mb-1">Active Applications</p>
                        <h3 class="fw-bold mb-0">{{ $stats['total_active'] }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card shadow-sm border-0 h-100" wire:click="setFilterCategory('provider')" style="cursor:pointer">
                    <div class="card-body {{ $filterCategory === 'provider' ? 'border border-warning' : '' }}">
                        <p class="text-uppercase text-muted small mb-1">Pending Provider</p>
                        <h3 class="fw-bold mb-0 text-warning">{{ $stats['pending_provider'] }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card shadow-sm border-0 h-100" wire:click="setFilterCategory('payer')" style="cursor:pointer">
                    <div class="card-body {{ $filterCategory === 'payer' ? 'border border-info' : '' }}">
                        <p class="text-uppercase text-muted small mb-1">Pending Payer</p>
                        <h3 class="fw-bold mb-0">{{ $stats['pending_payer'] }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card shadow-sm border-0 h-100" wire:click="setFilterCategory('overdue')" style="cursor:pointer">
                    <div class="card-body {{ $filterCategory === 'overdue' ? 'border border-danger' : '' }}">
                        <p class="text-uppercase text-muted small mb-1">Overdue</p>
                        <h3 class="fw-bold mb-0 text-danger">{{ $stats['overdue'] }}</h3>
                    </div>
                </div>
            </div>
        </div>

        {{-- Filters --}}
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small text-muted mb-1">Search</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="ti tabler-search"></i></span>
                            <input type="search" wire:model.live.debounce.300ms="caseSearch" class="form-control"
                                placeholder="Case #, provider, practice, NPI, payer..."
                                autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-muted mb-1">Practice</label>
                        <select wire:model.live="filterPracticeId" class="form-select">
                            <option value="">All Practices</option>
                            @foreach ($practices as $practice)
                                <option value="{{ $practice->id }}">
                                    {{ $practice->legal_name }}@if($practice->client_code) ({{ $practice->client_code }})@endif
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-muted mb-1">Provider</label>
                        <select wire:model.live="filterProviderId" class="form-select" @disabled(! $filterPracticeId)>
                            <option value="">{{ $filterPracticeId ? 'All Providers' : 'Select a practice first' }}</option>
                            @foreach ($providers as $provider)
                                <option value="{{ $provider->id }}">{{ $provider->user->name ?? 'Provider #'.$provider->id }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-muted mb-1">Payer</label>
                        <select wire:model.live="filterPayerId" class="form-select">
                            <option value="">All Payers</option>
                            @foreach ($payers as $payer)
                                <option value="{{ $payer->id }}">{{ $payer->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="row g-3 align-items-end mt-1">
                    <div class="col-md-3">
                        <label class="form-label small text-muted mb-1">Status</label>
                        <select wire:model.live="filterStatusId" class="form-select">
                            <option value="">All Statuses</option>
                            @foreach ($statuses as $status)
                                <option value="{{ $status->id }}">{{ $status->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-muted mb-1">Assigned To (Staff)</label>
                        <select wire:model.live="filterOwnerId" class="form-select">
                            <option value="">All Owners</option>
                            @foreach ($admins as $admin)
                                <option value="{{ $admin->id }}">{{ $admin->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small text-muted mb-1">State</label>
                        <input type="text" wire:model.live.debounce.300ms="filterState" class="form-control" placeholder="e.g. TX" maxlength="2">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small text-muted mb-1">Revalidation Due</label>
                        <select wire:model.live="filterRevalidation" class="form-select">
                            <option value="">Any</option>
                            <option value="30">Next 30 days</option>
                            <option value="60">Next 60 days</option>
                            <option value="90">Next 90 days</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="button" wire:click="clearFilters"
                            class="btn btn-outline-secondary w-100"
                            @disabled(! $this->hasActiveFilters())>
                            Clear
                        </button>
                    </div>
                </div>
                <div class="row g-3 align-items-end mt-1">
                    <div class="col-md-3 d-flex align-items-end">
                        <div class="form-check mb-2">
                            <input type="checkbox" wire:model.live="filterRecentlySubmitted" class="form-check-input" id="recentSubmitted">
                            <label class="form-check-label small" for="recentSubmitted">Recently submitted (14d)</label>
                        </div>
                    </div>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
                    <button type="button" wire:click="setFilterCategory('escalated')" class="btn btn-sm {{ $filterCategory === 'escalated' ? 'btn-danger' : 'btn-outline-danger' }}">Escalated</button>
                    <button type="button" wire:click="setFilterCategory('internal')" class="btn btn-sm {{ $filterCategory === 'internal' ? 'btn-primary' : 'btn-outline-primary' }}">Internal</button>
                    <button type="button" wire:click="setFilterCategory('approved')" class="btn btn-sm {{ $filterCategory === 'approved' ? 'btn-success' : 'btn-outline-success' }}">Approved</button>
                    @if ($this->hasActiveFilters())
                        <span class="text-muted small ms-auto">
                            {{ $cases->total() }} result{{ $cases->total() !== 1 ? 's' : '' }}
                        </span>
                    @endif
                </div>
            </div>
        </div>

        {{-- Applications Table --}}
        <div class="card shadow-sm border-0" wire:loading.class="opacity-50" wire:target="caseSearch,filterPayerId,filterPracticeId,filterProviderId,filterStatusId,filterOwnerId,filterState,filterRevalidation,filterRecentlySubmitted,filterCategory,clearFilters,setFilterCategory,updateCaseStatus,inlineUpdate">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Case #</th>
                            <th>Provider</th>
                            <th>Payer</th>
                            <th>Status</th>
                            <th>Assigned To</th>
                            <th>Delay Type</th>
                            <th>Aging</th>
                            <th>Next Follow-up</th>
                            <th>Tasks</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody wire:key="case-rows-{{ md5($caseSearch.$filterPayerId.$filterPracticeId.$filterProviderId.$filterStatusId.$filterCategory.$filterOwnerId.$filterState.$cases->currentPage()) }}">
                        @forelse($cases as $case)
                            @php
                                $isOverdue = $case->isOverdue();
                            @endphp
                            <tr wire:key="case-row-{{ $case->id }}" class="{{ $case->is_escalated ? 'table-danger' : ($isOverdue ? 'table-warning' : '') }}">
                                <td>
                                    <span class="fw-semibold">{{ $case->case_number }}</span>
                                    @if($case->is_escalated)<span class="badge bg-danger ms-1">Escalated</span>@endif
                                </td>
                                <td>
                                    <a href="{{ route('admin.providers.show', $case->provider_id) }}" class="fw-semibold">
                                        {{ $case->provider?->user?->name ?? 'N/A' }}
                                    </a>
                                    <small class="text-muted d-block">{{ $case->practice?->legal_name ?? '' }}</small>
                                </td>
                                <td>{{ $case->payer?->name ?? 'N/A' }}</td>
                                <td style="min-width: 200px;">
                                    <select class="form-select form-select-sm" wire:change="updateCaseStatus({{ $case->id }}, $event.target.value)">
                                        @foreach ($statuses as $status)
                                            <option value="{{ $status->id }}" @selected($case->status_id == $status->id)>{{ $status->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td style="min-width: 140px;">
                                    <select class="form-select form-select-sm" wire:change="inlineUpdate({{ $case->id }}, 'assigned_admin_id', $event.target.value)">
                                        <option value="">Unassigned</option>
                                        @foreach ($admins as $admin)
                                            <option value="{{ $admin->id }}" @selected($case->assigned_admin_id == $admin->id)>{{ $admin->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td style="min-width: 130px;">
                                    <select class="form-select form-select-sm" wire:change="inlineUpdate({{ $case->id }}, 'delay_owner_id', $event.target.value)">
                                        <option value="">—</option>
                                        @foreach ($delayOwners as $owner)
                                            <option value="{{ $owner->id }}" @selected($case->delay_owner_id == $owner->id)>{{ $owner->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td><span class="fw-semibold">{{ $case->aging_days }}d</span></td>
                                <td>
                                    @if($case->next_follow_up_date)
                                        <small class="{{ $isOverdue ? 'text-danger fw-bold' : '' }}">{{ $case->next_follow_up_date->format('m/d/Y') }}</small>
                                    @else
                                        <small class="text-muted">—</small>
                                    @endif
                                </td>
                                <td>
                                    @if($case->open_tasks_count > 0)
                                        <a href="{{ route('admin.tasks.kanban', ['case_id' => $case->id]) }}"
                                            class="badge bg-label-warning text-decoration-none">
                                            {{ $case->open_tasks_count }} open
                                        </a>
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>
                                <td class="text-end text-nowrap">
                                    <div class="d-inline-flex gap-1">
                                        <a href="{{ route('admin.credentials.show', $case) }}"
                                            class="btn btn-sm btn-outline-primary"
                                            title="View application details">
                                            <i class="ti tabler-eye me-1"></i>View
                                        </a>
                                        {{-- <button type="button"
                                            wire:click.stop="toggleEscalation({{ $case->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="toggleEscalation({{ $case->id }})"
                                            class="btn btn-sm {{ $case->is_escalated ? 'btn-danger' : 'btn-outline-danger' }}"
                                            title="{{ $case->is_escalated ? 'Remove escalation' : 'Escalate application' }}">
                                            <span wire:loading.remove wire:target="toggleEscalation({{ $case->id }})">
                                                <i class="ti tabler-flag me-1"></i>{{ $case->is_escalated ? 'De-escalate' : 'Escalate' }}
                                            </span>
                                            <span wire:loading wire:target="toggleEscalation({{ $case->id }})" class="spinner-border spinner-border-sm" role="status"></span>
                                        </button> --}}
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center py-5 text-muted">
                                    <i class="ti tabler-clipboard-off d-block mb-2" style="font-size:2rem"></i>
                                    No applications found. Create applications from a Practice or Provider detail page.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($cases->hasPages())
                <div class="card-footer bg-white py-2">{{ $cases->withQueryString()->links('livewire::bootstrap') }}</div>
            @endif
        </div>
</div>
