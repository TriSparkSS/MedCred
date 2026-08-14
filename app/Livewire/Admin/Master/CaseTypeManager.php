<?php

namespace App\Livewire\Admin\Master;

use App\Models\CaseType;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;

#[Layout('layouts::admin', ['title' => 'Case Types | Settings'])]
class CaseTypeManager extends Component
{
    use WithPagination;
    public $search = '';
    public $showModal = false;
    public $modalMode = 'create';
    public $formData = [];
    public $caseTypeId = null;
    public $sortField = 'name';
    public $sortDirection = 'asc';

    public function render()
    {
        $query = CaseType::query();

        if ($this->search) {
            $query->where('name', 'like', '%' . $this->search . '%');
        }

        $query->orderBy($this->sortField, $this->sortDirection);
        $records = $query->paginate(10);

        return view('livewire.admin.master.case-type-manager', [
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
        $record = CaseType::findOrFail($id);
        $this->caseTypeId = $id;
        $this->modalMode = 'edit';
        $this->formData = [
            'name' => $record->name,
            'is_active' => $record->is_active,
        ];
        $this->showModal = true;
    }

    protected $rules = [
        'formData.name' => 'required|string|max:255|unique:case_types,name',
        'formData.is_active' => 'boolean',
    ];

    public function save()
    {
        if ($this->modalMode === 'edit') {
            $this->rules['formData.name'] = 'required|string|max:255|unique:case_types,name,' . $this->caseTypeId;
        }

        $this->validate();

        if ($this->modalMode === 'create') {
            CaseType::create($this->formData);
            flash()->success('Case Type created successfully!');
        } else {
            $record = CaseType::findOrFail($this->caseTypeId);
            $record->update($this->formData);
            flash()->success('Case Type updated successfully!');
        }

        $this->resetForm();
        $this->closeModal();
    }

    public function delete(int $id): void
    {
        $this->caseTypeId = $id;
        sweetalert()
            ->showDenyButton()
            ->info('Are you sure you want to delete the case type?');
    }

    #[\Livewire\Attributes\On('sweetalert:confirmed')]
    public function onConfirmed(array $payload): void
    {
        $record = CaseType::findOrFail($this->caseTypeId);
        $record->delete();
        $this->caseTypeId = null;
        flash()->info('Case Type successfully deleted.');
    }

    #[\Livewire\Attributes\On('sweetalert:denied')]
    public function onDeny(array $payload): void
    {
        $this->caseTypeId = null;
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
        $this->caseTypeId = null;
        $this->resetValidation();
    }
}
