<?php

namespace App\Livewire\Admin\Setting\Specialty;

use Livewire\Component;
use App\Models\Specialty;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Livewire\Attributes\On;

#[Layout('layouts::admin', ['title' => 'Specialties | Settings'])]
class SpecialtyListPage extends Component
{
    use WithPagination;

    public $search = '';
    public $showModal = false;
    public $modalMode = 'create'; // 'create' or 'edit'
    public $formData = [];
    public $specialtyId = null;
    public $showDeleteConfirm = false;

    protected $rules = [
        'formData.name' => 'required|string|max:255|unique:specialties,name',
        'formData.description' => 'nullable|string|max:1000',
    ];

    public function updated($propertyName)
    {
        if ($propertyName === 'search') {
            $this->resetPage();
        }
    }

    public function openCreateModal()
    {
        $this->resetForm();
        $this->modalMode = 'create';
        $this->showModal = true;
    }

    public function openEditModal($specialtyId)
    {
        $specialty = Specialty::findOrFail($specialtyId);
        $this->specialtyId = $specialtyId;
        $this->modalMode = 'edit';
        $this->formData = [
            'name' => $specialty->name,
            'description' => $specialty->description,
        ];
        $this->showModal = true;
    }

    public function save()
    {
        if ($this->modalMode === 'edit') {
            $this->rules['formData.name'] = 'required|string|max:255|unique:specialties,name,' . $this->specialtyId;
        }

        $this->validate();

        if ($this->modalMode === 'create') {
            Specialty::create($this->formData);
            flash()->success('Specialty created successfully!');
        } else {
            $specialty = Specialty::findOrFail($this->specialtyId);
            $specialty->update($this->formData);

            flash()->success('Specialty updated successfully!');
        }

        $this->resetForm();
        $this->closeModal();
    }

    public function delete(int $id): void
    {
        $this->specialtyId = $id;
        sweetalert()
            ->showDenyButton()
            ->info('Are you sure you want to delete the specialty?');
    }

    #[On('sweetalert:confirmed')]
    public function onConfirmed(array $payload): void
    {
        $specialty = Specialty::findOrFail($this->specialtyId);
        $specialty->delete();
        $this->specialtyId = null;
        flash()->info('Specialty successfully deleted.');
    }

    #[On('sweetalert:denied')]
    public function onDeny(array $payload): void
    {
        $this->specialtyId = null;
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
        $this->specialtyId = null;
        $this->resetValidation();
    }

    public function render()
    {
        $query = Specialty::query();

        if ($this->search) {
            $query->where('name', 'like', '%' . $this->search . '%')
                ->orWhere('description', 'like', '%' . $this->search . '%');
        }

        $query->orderBy('created_at', 'desc');
        $specialties = $query->paginate(10);

        $stats = [
            'total' => Specialty::count(),
            'active' => Specialty::count(),
        ];

        return view('livewire.admin.setting.specialty.specialty-list-page', compact('specialties', 'stats'));
    }
}
