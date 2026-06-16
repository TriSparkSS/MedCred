<?php

namespace App\Livewire\Admin\Master;

use App\Models\Priority;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;

#[Layout('layouts::admin', ['title' => 'Priorities | Settings'])]
class PriorityManager extends Component
{
    use WithPagination;

    public $search = '';
    public $showModal = false;
    public $modalMode = 'create';
    public $formData = [];
    public $priorityId = null;
    public $sortField = 'name';
    public $sortDirection = 'asc';

    public function render()
    {
        $query = Priority::query();

        if ($this->search) {
            $query->where('name', 'like', '%' . $this->search . '%');
        }

        $query->orderBy($this->sortField, $this->sortDirection);
        $records = $query->paginate(10);

        return view('livewire.admin.master.priority-manager', [
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
        $record = Priority::findOrFail($id);
        $this->priorityId = $id;
        $this->modalMode = 'edit';
        $this->formData = [
            'name' => $record->name,
            'sort_order' => $record->sort_order,
            'is_active' => $record->is_active,
        ];
        $this->showModal = true;
    }

    protected $rules = [
        'formData.name' => 'required|string|max:255|unique:priorities,name',
        'formData.sort_order' => 'required|integer',
        'formData.is_active' => 'boolean',
    ];

    public function save()
    {
        if ($this->modalMode === 'edit') {
            $this->rules['formData.name'] = 'required|string|max:255|unique:priorities,name,' . $this->priorityId;
        }

        $this->validate();

        if ($this->modalMode === 'create') {
            Priority::create($this->formData);
            flash()->success('Priority created successfully!');
        } else {
            $record = Priority::findOrFail($this->priorityId);
            $record->update($this->formData);
            flash()->success('Priority updated successfully!');
        }

        $this->resetForm();
        $this->closeModal();
    }

    public function delete(int $id): void
    {
        $this->priorityId = $id;
        sweetalert()
            ->showDenyButton()
            ->info('Are you sure you want to delete the priority?');
    }

    #[\Livewire\Attributes\On('sweetalert:confirmed')]
    public function onConfirmed(array $payload): void
    {
        $record = Priority::findOrFail($this->priorityId);
        $record->delete();
        $this->priorityId = null;
        flash()->info('Priority successfully deleted.');
    }

    #[\Livewire\Attributes\On('sweetalert:denied')]
    public function onDeny(array $payload): void
    {
        $this->priorityId = null;
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
        $this->priorityId = null;
        $this->resetValidation();
    }
}
