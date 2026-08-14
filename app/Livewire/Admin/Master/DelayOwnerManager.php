<?php

namespace App\Livewire\Admin\Master;

use App\Models\DelayOwner;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;

#[Layout('layouts::admin', ['title' => 'Delay Owners | Settings'])]
class DelayOwnerManager extends Component
{
    use WithPagination;

    public $search = '';
    public $showModal = false;
    public $modalMode = 'create';
    public $formData = [];
    public $delayOwnerId = null;
    public $sortField = 'name';
    public $sortDirection = 'asc';

    public function render()
    {
        $query = DelayOwner::query();

        if ($this->search) {
            $query->where('name', 'like', '%' . $this->search . '%');
        }

        $query->orderBy($this->sortField, $this->sortDirection);
        $records = $query->paginate(10);

        return view('livewire.admin.master.delay-owner-manager', [
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
        $record = DelayOwner::findOrFail($id);
        $this->delayOwnerId = $id;
        $this->modalMode = 'edit';
        $this->formData = [
            'name' => $record->name,
            'is_active' => $record->is_active,
        ];
        $this->showModal = true;
    }

    protected $rules = [
        'formData.name' => 'required|string|max:255|unique:delay_owners,name',
        'formData.is_active' => 'boolean',
    ];

    public function save()
    {
        if ($this->modalMode === 'edit') {
            $this->rules['formData.name'] = 'required|string|max:255|unique:delay_owners,name,' . $this->delayOwnerId;
        }

        $this->validate();

        if ($this->modalMode === 'create') {
            DelayOwner::create($this->formData);
            flash()->success('Delay Owner created successfully!');
        } else {
            $record = DelayOwner::findOrFail($this->delayOwnerId);
            $record->update($this->formData);
            flash()->success('Delay Owner updated successfully!');
        }

        $this->resetForm();
        $this->closeModal();
    }

    public function delete(int $id): void
    {
        $this->delayOwnerId = $id;
        sweetalert()
            ->showDenyButton()
            ->info('Are you sure you want to delete the delay owner?');
    }

    #[\Livewire\Attributes\On('sweetalert:confirmed')]
    public function onConfirmed(array $payload): void
    {
        $record = DelayOwner::findOrFail($this->delayOwnerId);
        $record->delete();
        $this->delayOwnerId = null;
        flash()->info('Delay Owner successfully deleted.');
    }

    #[\Livewire\Attributes\On('sweetalert:denied')]
    public function onDeny(array $payload): void
    {
        $this->delayOwnerId = null;
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
        $this->delayOwnerId = null;
        $this->resetValidation();
    }
}
