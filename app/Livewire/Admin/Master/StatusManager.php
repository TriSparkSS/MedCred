<?php

namespace App\Livewire\Admin\Master;

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
    public $sortField = 'name';
    public $sortDirection = 'asc';

    public function render()
    {
        $query = Status::query();

        if ($this->search) {
            $query->where('name', 'like', '%' . $this->search . '%');
        }

        $query->orderBy($this->sortField, $this->sortDirection);
        $records = $query->paginate(10);

        return view('livewire.admin.master.status-manager', [
            'records' => $records
        ]);
    }

    public function updated($propertyName)
    {
        if ($propertyName === 'search') {
            $this->resetPage();
        }
    }

    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === "asc" ? "desc" : "asc";
        } else {
            $this->sortField = $field;
            $this->sortDirection = "asc";
        }
    }

    public function openCreateModal()
    {
        $this->resetForm();
        $this->modalMode = 'create';
        $this->showModal = true;
    }

    public function openEditModal($id)
    {
        $record = Status::findOrFail($id);
        $this->statusId = $id;
        $this->modalMode = 'edit';
        $this->formData = [
            'name' => $record->name,
            'is_active' => $record->is_active,
        ];
        $this->showModal = true;
    }

    protected $rules = [
        'formData.name' => 'required|string|max:255|unique:statuses,name',
        'formData.is_active' => 'boolean',
    ];

    public function save()
    {
        if ($this->modalMode === 'edit') {
            $this->rules['formData.name'] = 'required|string|max:255|unique:statuses,name,' . $this->statusId;
        }

        $this->validate();

        if ($this->modalMode === 'create') {
            Status::create($this->formData);
            flash()->success('Status created successfully!');
        } else {
            $status = Status::findOrFail($this->statusId);
            $status->update($this->formData);
            flash()->success('Status updated successfully!');
        }

        $this->resetForm();
        $this->closeModal();
    }

    public function delete(int $id): void
    {
        $this->statusId = $id;
        sweetalert()
            ->showDenyButton()
            ->info('Are you sure you want to delete the status?');
    }

    #[\Livewire\Attributes\On('sweetalert:confirmed')]
    public function onConfirmed(array $payload): void
    {
        $record = Status::findOrFail($this->statusId);
        $record->delete();
        $this->statusId = null;
        flash()->info('Status successfully deleted.');
    }

    #[\Livewire\Attributes\On('sweetalert:denied')]
    public function onDeny(array $payload): void
    {
        $this->statusId = null;
        flash()->info('Deletion cancelled.');
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
    }

    private function resetForm()
    {
        $this->formData = [];
        $this->statusId = null;
        $this->resetValidation();
    }
}
