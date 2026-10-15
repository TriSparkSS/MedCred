<div>
    <style>[x-cloak]{display:none !important}</style>
    <div class="container-fluid flex-grow-1 px-3 px-md-4 py-3 py-md-4">
        {{-- Header --}}
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-start gap-3">
                <div>
                    <h3 class="fw-bold text-primary mb-1">{{ $isApplicationsHub ? 'Applications' : 'Credentialing Tracker' }}</h3>
                    <p class="text-muted mb-0">
                        {{ $isApplicationsHub
                            ? 'Review and create payer enrollment applications for practices and providers.'
                            : 'Track payer enrollment applications, status, and follow-ups.' }}
                    </p>
                </div>
                @if ($canCreateCredentials)
                    <a href="{{ route('admin.credentials.create') }}" class="btn btn-primary">
                        <i class="ti tabler-file-plus me-1"></i> New Application
                    </a>
                @endif
            </div>
        </div>

        {{-- KPI Stats --}}
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-lg-3">
                <div class="card shadow-sm border-0 h-100" wire:click="setFilterCategory('active')" role="button" style="cursor:pointer">
                    <div class="card-body {{ $filterCategory === 'active' ? 'border border-primary' : '' }}">
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
        <div class="card shadow-sm border-0 mb-4 overflow-visible">
            <div class="card-body overflow-visible">
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
                        <select wire:model.live="filterProviderId" class="form-select">
                            <option value="">All Providers</option>
                            @foreach ($providers as $provider)
                                <option value="{{ $provider->id }}">{{ $provider->user->name ?? 'Provider #'.$provider->id }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-muted mb-1">Enrollment</label>
                        <select wire:model.live="filterEnrollment" class="form-select">
                            <option value="">All types</option>
                            @foreach ($enrollmentLevels as $level)
                                <option value="{{ $level->value }}">{{ $level->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3"
                        x-data="{ open: false, q: '' }"
                        @keydown.escape.window="open = false"
                        @click.outside="open = false">
                        <label class="form-label small text-muted mb-1">Payer</label>
                        <div class="dropdown w-100">
                            <button type="button"
                                class="form-select text-start"
                                :class="{ 'show': open }"
                                :aria-expanded="open"
                                @click="open = !open; if (open) { q = ''; $nextTick(() => $refs.payerSearch?.focus()) }">
                                {{ $filterPayerId ? ($payers->firstWhere('id', (int) $filterPayerId)->name ?? 'All Payers') : 'All Payers' }}
                            </button>
                            <div x-show="open"
                                x-cloak
                                x-transition
                                class="dropdown-menu show w-100 mt-1 p-0 shadow"
                                style="z-index: 1080; max-height: 18rem;"
                                @click.stop
                                @mousedown.stop>
                                <div class="px-2 pt-2 pb-1 border-bottom bg-white sticky-top">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-white"><i class="ti tabler-search"></i></span>
                                        <input type="search" x-ref="payerSearch" x-model="q"
                                            class="form-control"
                                            placeholder="Search payer name..."
                                            autocomplete="off">
                                    </div>
                                </div>
                                <div class="py-1 overflow-auto" style="max-height: 13.5rem;">
                                    <button type="button"
                                        class="dropdown-item {{ $filterPayerId === '' ? 'active' : '' }}"
                                        wire:click="$set('filterPayerId', '')"
                                        @click="open = false; q = ''">
                                        All Payers
                                    </button>
                                    @foreach ($payers as $payer)
                                        <button type="button"
                                            class="dropdown-item {{ (string) $filterPayerId === (string) $payer->id ? 'active' : '' }}"
                                            x-show="!q || {{ \Illuminate\Support\Js::from(mb_strtolower($payer->name)) }}.includes(q.toLowerCase())"
                                            wire:click="$set('filterPayerId', '{{ $payer->id }}')"
                                            @click="open = false; q = ''">
                                            {{ $payer->name }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        </div>
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
        <div class="card shadow-sm border-0" wire:loading.class="opacity-50" wire:target="caseSearch,filterPayerId,filterPracticeId,filterProviderId,filterStatusId,filterOwnerId,filterState,filterRevalidation,filterRecentlySubmitted,filterEnrollment,filterCategory,clearFilters,setFilterCategory,updateCaseStatus,inlineUpdate,toggleEscalation,bulkAssignSelected">
            @if ($canAssignCredentials)
                <div class="card-body border-bottom py-3 d-flex flex-wrap align-items-center gap-2">
                    <span class="small text-muted me-1">{{ count($selectedCaseIds) }} selected</span>
                    <select wire:model="bulkAssigneeId" class="form-select form-select-sm" style="width: auto; min-width: 180px;">
                        <option value="">Unassigned</option>
                        @foreach ($admins as $admin)
                            <option value="{{ $admin->id }}">{{ $admin->name }}</option>
                        @endforeach
                    </select>
                    <button type="button" wire:click="bulkAssignSelected" class="btn btn-sm btn-primary" @disabled(count($selectedCaseIds) === 0)>
                        Assign selected
                    </button>
                </div>
            @endif
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            @if ($canAssignCredentials)
                                @php $pageCaseIds = $cases->pluck('id')->map(fn ($id) => (string) $id)->all(); @endphp
                                <th style="width: 36px;">
                                    <input type="checkbox" class="form-check-input"
                                        wire:click="toggleSelectPage(@js($pageCaseIds))"
                                        @checked(count($pageCaseIds) && collect($pageCaseIds)->every(fn ($id) => in_array($id, $selectedCaseIds, true)))>
                                </th>
                            @endif
                            <th>Case #</th>
                            <th>Type</th>
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
                    <tbody>
                        @forelse($cases as $case)
                            @php
                                $isOverdue = $case->isOverdue();
                            @endphp
                            <tr wire:key="case-row-{{ $case->id }}-{{ $case->is_escalated ? 'esc' : 'normal' }}" class="{{ $case->is_escalated ? 'table-danger' : ($isOverdue ? 'table-warning' : '') }}">
                                @if ($canAssignCredentials)
                                    <td>
                                        <input type="checkbox" class="form-check-input" value="{{ $case->id }}" wire:model.live="selectedCaseIds">
                                    </td>
                                @endif
                                <td>
                                    <a href="{{ route('admin.credentials.show', $case->id) }}" class="fw-semibold text-primary text-decoration-underline">{{ $case->case_number }}</a>
                                    @if($case->is_escalated)<span class="badge bg-danger ms-1">Escalated</span>@endif
                                </td>
                                <td>
                                    <span class="badge bg-label-{{ $case->enrollment_level === \App\Enums\EnrollmentLevel::Group ? 'info' : 'secondary' }}">
                                        {{ $case->enrollment_level?->label() ?? 'Individual' }}
                                    </span>
                                </td>
                                <td>
                                    <a href="{{ route('admin.providers.show', $case->provider_id) }}" class="fw-semibold">
                                        {{ $case->provider?->user?->name ?? 'N/A' }}
                                    </a>
                                    <small class="text-muted d-block">{{ $case->practice?->legal_name ?? '' }}</small>
                                </td>
                                <td>{{ $case->payer?->name ?? 'N/A' }}</td>
                                <td style="min-width: 200px;">
                                    @if ($canEditCredentials)
                                        <select class="form-select form-select-sm" wire:change="updateCaseStatus({{ $case->id }}, $event.target.value)">
                                            @foreach ($statuses as $status)
                                                <option value="{{ $status->id }}" @selected($case->status_id == $status->id)>{{ $status->name }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <span class="badge bg-label-secondary">{{ $case->status?->name ?? 'N/A' }}</span>
                                    @endif
                                </td>
                                <td style="min-width: 140px;">
                                    @if ($canAssignCredentials)
                                        <select class="form-select form-select-sm" wire:change="inlineUpdate({{ $case->id }}, 'assigned_admin_id', $event.target.value)">
                                            <option value="">Unassigned</option>
                                            @foreach ($admins as $admin)
                                                <option value="{{ $admin->id }}" @selected($case->assigned_admin_id == $admin->id)>{{ $admin->name }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <span class="small text-muted">{{ $case->assignedAdmin?->name ?? 'Unassigned' }}</span>
                                    @endif
                                </td>
                                <td style="min-width: 130px;">
                                    @if ($canOverrideDelay)
                                        <select class="form-select form-select-sm" wire:change="inlineUpdate({{ $case->id }}, 'delay_owner_id', $event.target.value)">
                                            <option value="">—</option>
                                            @foreach ($delayOwners as $owner)
                                                <option value="{{ $owner->id }}" @selected($case->delay_owner_id == $owner->id)>{{ $owner->name }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <span class="small text-muted">{{ $case->delayOwner?->name ?? '—' }}</span>
                                    @endif
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
                                        @if ($canEditCredentials)
                                            <a href="{{ route('admin.credentials.edit', $case) }}"
                                                class="btn btn-sm btn-outline-secondary"
                                                title="Edit application">
                                                <i class="ti tabler-edit me-1"></i>Edit
                                            </a>
                                        @endif
                                        @if ($canEscalateTasks)
                                        <button type="button"
                                            wire:click="toggleEscalation({{ $case->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="toggleEscalation"
                                            class="btn btn-sm {{ $case->is_escalated ? 'btn-danger' : 'btn-outline-danger' }}"
                                            title="{{ $case->is_escalated ? 'Remove escalation' : 'Escalate application' }}">
                                            <span wire:loading.remove wire:target="toggleEscalation">
                                                <i class="ti tabler-flag me-1"></i>{{ $case->is_escalated ? 'De-escalate' : 'Escalate' }}
                                            </span>
                                            <span wire:loading wire:target="toggleEscalation" class="spinner-border spinner-border-sm" role="status"></span>
                                        </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $canAssignCredentials ? 11 : 10 }}" class="text-center py-5 text-muted">
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
</div>
