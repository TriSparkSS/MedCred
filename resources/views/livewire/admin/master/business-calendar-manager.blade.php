<div class="container-fluid px-3 px-md-4 py-4">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <div class="row align-items-center g-3">
                <div class="col-lg-3">
                    <h4 class="mb-1 fw-bold text-primary">Business Calendars</h4>
                    <small class="text-muted">Manage business days and holidays</small>
                </div>
                <div class="col-lg-6">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="ti tabler-search"></i></span>
                        <input type="search" wire:model.live="search" class="form-control border-start-0" placeholder="Search calendars...">
                    </div>
                </div>
                <div class="col-lg-3 text-lg-end">
                    <button wire:click="openCreateCalendarModal" class="btn btn-primary"><i class="ti tabler-plus me-1"></i>Add Calendar</button>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white">
                    <h5 class="mb-0 fw-semibold">Calendars</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Name</th>
                                <th>Default</th>
                                <th width="100" class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($calendars as $calendar)
                                <tr class="{{ $selectedCalendarId === $calendar->id ? 'table-primary' : '' }}" wire:click="selectCalendar({{ $calendar->id }})" style="cursor: pointer;">
                                    <td>
                                        <div class="fw-semibold">{{ $calendar->name }}</div>
                                        <small class="text-muted">{{ $calendar->is_active ? 'Active' : 'Inactive' }}{{ $calendar->exclude_weekends ? ' · Excludes weekends' : '' }}</small>
                                    </td>
                                    <td>
                                        @if ($calendar->is_default)
                                            <span class="badge bg-label-primary">Default</span>
                                        @endif
                                    </td>
                                    <td class="text-end" wire:click.stop>
                                        <button wire:click="openEditCalendarModal({{ $calendar->id }})" class="btn btn-sm btn-icon btn-outline-primary"><i class="ti tabler-edit"></i></button>
                                        <button wire:click="deleteCalendar({{ $calendar->id }})" class="btn btn-sm btn-icon btn-outline-danger"><i class="ti tabler-trash"></i></button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center py-4 text-muted">No calendars found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer bg-white">{{ $calendars->links() }}</div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0 fw-semibold">Holidays</h5>
                        <small class="text-muted">{{ $selectedCalendar?->name ?? 'Select a calendar' }}</small>
                    </div>
                    @if ($selectedCalendar)
                        <button wire:click="openCreateHolidayModal" class="btn btn-sm btn-primary"><i class="ti tabler-plus me-1"></i>Add Holiday</button>
                    @endif
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Name</th>
                                <th width="100" class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($selectedCalendar?->holidays ?? [] as $holiday)
                                <tr>
                                    <td>{{ $holiday->date->format('M j, Y') }}</td>
                                    <td>{{ $holiday->name ?: '—' }}</td>
                                    <td class="text-end">
                                        <button wire:click="openEditHolidayModal({{ $holiday->id }})" class="btn btn-sm btn-icon btn-outline-primary"><i class="ti tabler-edit"></i></button>
                                        <button wire:click="deleteHoliday({{ $holiday->id }})" class="btn btn-sm btn-icon btn-outline-danger"><i class="ti tabler-trash"></i></button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center py-5 text-muted">No holidays for this calendar.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @if ($showCalendarModal)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,.5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ $calendarModalMode === 'create' ? 'Create Calendar' : 'Edit Calendar' }}</h5>
                        <button type="button" class="btn-close" wire:click="closeCalendarModal"></button>
                    </div>
                    <form wire:submit.prevent="saveCalendar">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Name</label>
                                <input type="text" wire:model="calendarFormData.name" class="form-control @error('calendarFormData.name') is-invalid @enderror">
                                @error('calendarFormData.name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="form-check form-switch mb-2">
                                <input type="checkbox" class="form-check-input" id="excludeWeekends" wire:model="calendarFormData.exclude_weekends">
                                <label class="form-check-label" for="excludeWeekends">Exclude Weekends</label>
                            </div>
                            <div class="form-check form-switch mb-2">
                                <input type="checkbox" class="form-check-input" id="isDefaultCalendar" wire:model="calendarFormData.is_default">
                                <label class="form-check-label" for="isDefaultCalendar">Default Calendar</label>
                            </div>
                            <div class="form-check form-switch">
                                <input type="checkbox" class="form-check-input" id="calendarActive" wire:model="calendarFormData.is_active">
                                <label class="form-check-label" for="calendarActive">Active</label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" wire:click="closeCalendarModal">Cancel</button>
                            <button type="submit" class="btn btn-primary">{{ $calendarModalMode === 'create' ? 'Create' : 'Update' }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @if ($showHolidayModal)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,.5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ $holidayModalMode === 'create' ? 'Add Holiday' : 'Edit Holiday' }}</h5>
                        <button type="button" class="btn-close" wire:click="closeHolidayModal"></button>
                    </div>
                    <form wire:submit.prevent="saveHoliday">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Date</label>
                                <input type="date" wire:model="holidayFormData.date" class="form-control @error('holidayFormData.date') is-invalid @enderror">
                                @error('holidayFormData.date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Name</label>
                                <input type="text" wire:model="holidayFormData.name" class="form-control @error('holidayFormData.name') is-invalid @enderror" placeholder="Holiday name">
                                @error('holidayFormData.name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" wire:click="closeHolidayModal">Cancel</button>
                            <button type="submit" class="btn btn-primary">{{ $holidayModalMode === 'create' ? 'Add' : 'Update' }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
