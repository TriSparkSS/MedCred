<?php

namespace App\Livewire\Admin\Master;

use App\Models\DocumentType;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;

#[Layout('layouts::admin', ['title' => 'Document Types | Settings'])]
class DocumentTypeManager extends Component
{
    use WithPagination;

    public $search = '';
    public $showModal = false;
    public $modalMode = 'create';
    public $formData = [];
    public $documentTypeId = null;
    public $sortField = 'name';
    public $sortDirection = 'asc';

    public function render()
    {
        $query = DocumentType::query();

        if ($this->search) {
            $query->where('name', 'like', '%' . $this->search . '%');
        }

        $query->orderBy($this->sortField, $this->sortDirection);
        $records = $query->paginate(10);

        return view('livewire.admin.master.document-type-manager', [
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
        $record = DocumentType::findOrFail($id);
        $this->documentTypeId = $id;
        $this->modalMode = 'edit';
        $this->formData = [
            'name' => $record->name,
            'is_active' => $record->is_active,
        ];
        $this->showModal = true;
    }

    protected $rules = [
        'formData.name' => 'required|string|max:255|unique:document_types,name',
        'formData.is_active' => 'boolean',
    ];

    public function save()
    {
        if ($this->modalMode === 'edit') {
            $this->rules['formData.name'] = 'required|string|max:255|unique:document_types,name,' . $this->documentTypeId;
        }

        $this->validate();

        if ($this->modalMode === 'create') {
            DocumentType::create($this->formData);
            flash()->success('Document Type created successfully!');
        } else {
            $record = DocumentType::findOrFail($this->documentTypeId);
            $record->update($this->formData);
            flash()->success('Document Type updated successfully!');
        }

        $this->resetForm();
        $this->closeModal();
    }

    public function delete(int $id): void
    {
        $this->documentTypeId = $id;
        sweetalert()
            ->showDenyButton()
            ->info('Are you sure you want to delete the document type?');
    }

    #[\Livewire\Attributes\On('sweetalert:confirmed')]
    public function onConfirmed(array $payload): void
    {
        $record = DocumentType::findOrFail($this->documentTypeId);
        $record->delete();
        $this->documentTypeId = null;
        flash()->info('Document Type successfully deleted.');
    }

    #[\Livewire\Attributes\On('sweetalert:denied')]
    public function onDeny(array $payload): void
    {
        $this->documentTypeId = null;
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
        $this->documentTypeId = null;
        $this->resetValidation();
    }
}
