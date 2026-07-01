<?php

namespace App\Livewire\Admin\Master;

use App\Models\BusinessCalendar;
use App\Models\Holiday;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::admin', ['title' => 'Business Calendar | Settings'])]
class BusinessCalendarManager extends Component
{
    use WithPagination;

    public $search = '';

    public $showCalendarModal = false;

    public $showHolidayModal = false;

    public $calendarModalMode = 'create';

    public $holidayModalMode = 'create';

    public $calendarFormData = [];

    public $holidayFormData = [];

    public $calendarId = null;

    public $holidayId = null;

    public $selectedCalendarId = null;

    public $pendingDelete = null;

    public function mount(): void
    {
        $this->selectedCalendarId = BusinessCalendar::default()?->id;
    }

    public function render()
    {
        $calendars = BusinessCalendar::when($this->search, fn ($q) => $q->where('name', 'like', '%' . $this->search . '%'))
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->paginate(10);

        $selectedCalendar = $this->selectedCalendarId
            ? BusinessCalendar::with(['holidays' => fn ($q) => $q->orderBy('date')])->find($this->selectedCalendarId)
            : null;

        return view('livewire.admin.master.business-calendar-manager', compact('calendars', 'selectedCalendar'));
    }

    public function updated($propertyName): void
    {
        if ($propertyName === 'search') {
            $this->resetPage();
        }
    }

    public function selectCalendar(int $id): void
    {
        $this->selectedCalendarId = $id;
    }

    public function openCreateCalendarModal(): void
    {
        $this->resetCalendarForm();
        $this->calendarModalMode = 'create';
        $this->calendarFormData = ['exclude_weekends' => true, 'is_default' => false, 'is_active' => true];
        $this->showCalendarModal = true;
    }

    public function openEditCalendarModal(int $id): void
    {
        $record = BusinessCalendar::findOrFail($id);
        $this->calendarId = $id;
        $this->calendarModalMode = 'edit';
        $this->calendarFormData = $record->only(['name', 'exclude_weekends', 'is_default', 'is_active']);
        $this->showCalendarModal = true;
    }

    public function saveCalendar(): void
    {
        $this->validate([
            'calendarFormData.name' => 'required|string|max:255',
            'calendarFormData.exclude_weekends' => 'boolean',
            'calendarFormData.is_default' => 'boolean',
            'calendarFormData.is_active' => 'boolean',
        ]);

        if ($this->calendarFormData['is_default'] ?? false) {
            BusinessCalendar::where('is_default', true)->update(['is_default' => false]);
        }

        if ($this->calendarModalMode === 'create') {
            $calendar = BusinessCalendar::create($this->calendarFormData);
            $this->selectedCalendarId = $calendar->id;
            flash()->success('Business calendar created successfully!');
        } else {
            BusinessCalendar::findOrFail($this->calendarId)->update($this->calendarFormData);
            flash()->success('Business calendar updated successfully!');
        }

        $this->closeCalendarModal();
    }

    public function deleteCalendar(int $id): void
    {
        $this->calendarId = $id;
        $this->pendingDelete = 'calendar';
        sweetalert()->showDenyButton()->info('Are you sure you want to delete this calendar and all its holidays?');
    }

    public function deleteHoliday(int $id): void
    {
        $this->holidayId = $id;
        $this->pendingDelete = 'holiday';
        sweetalert()->showDenyButton()->info('Are you sure you want to delete this holiday?');
    }

    #[On('sweetalert:confirmed')]
    public function onConfirmed(array $payload): void
    {
        if ($this->pendingDelete === 'calendar' && $this->calendarId) {
            $deletedId = $this->calendarId;
            BusinessCalendar::findOrFail($deletedId)->delete();

            if ($this->selectedCalendarId === $deletedId) {
                $this->selectedCalendarId = BusinessCalendar::default()?->id;
            }

            flash()->info('Business calendar successfully deleted.');
        } elseif ($this->pendingDelete === 'holiday' && $this->holidayId) {
            Holiday::findOrFail($this->holidayId)->delete();
            flash()->info('Holiday successfully deleted.');
        }

        $this->calendarId = null;
        $this->holidayId = null;
        $this->pendingDelete = null;
    }

    public function openCreateHolidayModal(): void
    {
        if (! $this->selectedCalendarId) {
            flash()->error('Select a calendar first.');

            return;
        }

        $this->resetHolidayForm();
        $this->holidayModalMode = 'create';
        $this->holidayFormData = ['business_calendar_id' => $this->selectedCalendarId];
        $this->showHolidayModal = true;
    }

    public function openEditHolidayModal(int $id): void
    {
        $record = Holiday::findOrFail($id);
        $this->holidayId = $id;
        $this->holidayModalMode = 'edit';
        $this->holidayFormData = $record->only(['business_calendar_id', 'date', 'name']);
        $this->showHolidayModal = true;
    }

    public function saveHoliday(): void
    {
        $uniqueRule = $this->holidayModalMode === 'edit'
            ? 'required|date|unique:holidays,date,' . $this->holidayId . ',id,business_calendar_id,' . ($this->holidayFormData['business_calendar_id'] ?? '')
            : 'required|date|unique:holidays,date,NULL,id,business_calendar_id,' . ($this->holidayFormData['business_calendar_id'] ?? '');

        $this->validate([
            'holidayFormData.business_calendar_id' => 'required|exists:business_calendars,id',
            'holidayFormData.date' => $uniqueRule,
            'holidayFormData.name' => 'nullable|string|max:255',
        ]);

        if ($this->holidayModalMode === 'create') {
            Holiday::create($this->holidayFormData);
            flash()->success('Holiday created successfully!');
        } else {
            Holiday::findOrFail($this->holidayId)->update($this->holidayFormData);
            flash()->success('Holiday updated successfully!');
        }

        $this->closeHolidayModal();
    }

    #[On('sweetalert:denied')]
    public function onDeny(array $payload): void
    {
        $this->calendarId = null;
        $this->holidayId = null;
        $this->pendingDelete = null;
        flash()->info('Deletion cancelled.');
    }

    public function closeCalendarModal(): void
    {
        $this->showCalendarModal = false;
        $this->resetCalendarForm();
    }

    public function closeHolidayModal(): void
    {
        $this->showHolidayModal = false;
        $this->resetHolidayForm();
    }

    private function resetCalendarForm(): void
    {
        $this->calendarFormData = [];
        $this->calendarId = null;
        $this->resetValidation();
    }

    private function resetHolidayForm(): void
    {
        $this->holidayFormData = [];
        $this->holidayId = null;
        $this->resetValidation();
    }
}
