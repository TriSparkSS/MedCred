<?php

namespace App\Livewire\Admin\Master;

use App\Models\DelayOwner;
use App\Models\Status;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;

#[Layout('layouts::admin', ['title' => 'Statuses | Settings'])]
class StatusManager extends Component
{
    use WithPagination;

    public $search = '';

    public $showModal = false;

    public $modalMode = 'create';

    public $formData = [];

    public $statusId = null;

    public function render()
    {
        $query = Status::with('delayOwner');

        if ($this->search) {
            $query->where('name', 'like', '%' . $this->search . '%');
        }

        $records = $query->orderBy('sort_order')->orderBy('name')->paginate(15);
        $delayOwners = DelayOwner::where('is_active', true)->orderBy('name')->get();
        $dashboardCategories = [
            'not_started' => 'Not Started',
            'internal' => 'Internal Review',
            'provider' => 'Pending Provider',
            'payer' => 'Pending Payer',
            'approved' => 'Approved',
            'closed' => 'Closed / Denied',
            'on_hold' => 'On Hold',
            'revalidation' => 'Revalidation',
        ];

        return view('livewire.admin.master.status-manager', compact('records', 'delayOwners', 'dashboardCategories'));
    }

    public function updated($propertyName): void
    {
        if ($propertyName === 'search') {
            $this->resetPage();
        }
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->modalMode = 'create';
        $this->formData = ['is_active' => true, 'sort_order' => Status::max('sort_order') + 1];
        $this->showModal = true;
    }

    public function openEditModal($id): void
    {
        $record = Status::findOrFail($id);
        $this->statusId = $id;
        $this->modalMode = 'edit';
        $this->formData = $record->only(['name', 'is_active', 'sort_order', 'dashboard_category', 'delay_owner_id']);
        $this->showModal = true;
    }

    protected function rules(): array
    {
        $uniqueRule = $this->modalMode === 'edit'
            ? 'required|string|max:255|unique:statuses,name,' . $this->statusId
            : 'required|string|max:255|unique:statuses,name';

        return [
            'formData.name' => $uniqueRule,
            'formData.is_active' => 'boolean',
            'formData.sort_order' => 'nullable|integer|min:0',
            'formData.dashboard_category' => 'nullable|string|max:50',
            'formData.delay_owner_id' => 'nullable|exists:delay_owners,id',
        ];
    }

    public function save(): void
    {
        $this->validate();

        if ($this->modalMode === 'create') {
            Status::create($this->formData);
            flash()->success('Status created successfully!');
        } else {
            Status::findOrFail($this->statusId)->update($this->formData);
            flash()->success('Status updated successfully!');
        }

        $this->closeModal();
    }

    public function moveUp(int $id): void
    {
        $status = Status::findOrFail($id);
        $previous = Status::where('sort_order', '<', $status->sort_order)->orderByDesc('sort_order')->first();

        if ($previous) {
            [$status->sort_order, $previous->sort_order] = [$previous->sort_order, $status->sort_order];
            $status->save();
            $previous->save();
        }
    }

    public function moveDown(int $id): void
    {
        $status = Status::findOrFail($id);
        $next = Status::where('sort_order', '>', $status->sort_order)->orderBy('sort_order')->first();

        if ($next) {
            [$status->sort_order, $next->sort_order] = [$next->sort_order, $status->sort_order];
            $status->save();
            $next->save();
        }
    }

    public function delete(int $id): void
    {
        $this->statusId = $id;
        sweetalert()->showDenyButton()->info('Are you sure you want to delete the status?');
    }

    #[\Livewire\Attributes\On('sweetalert:confirmed')]
    public function onConfirmed(array $payload): void
    {
        Status::findOrFail($this->statusId)->delete();
        $this->statusId = null;
        flash()->info('Status successfully deleted.');
    }

    #[\Livewire\Attributes\On('sweetalert:denied')]
    public function onDeny(array $payload): void
    {
        $this->statusId = null;
        flash()->info('Deletion cancelled.');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->formData = [];
        $this->statusId = null;
        $this->resetValidation();
    }
}
