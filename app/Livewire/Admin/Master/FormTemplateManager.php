<?php

namespace App\Livewire\Admin\Master;

use App\Models\FormTemplate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::admin', ['title' => 'Form Templates | Settings'])]
class FormTemplateManager extends Component
{
    use WithPagination;

    public $search = '';

    public $showModal = false;

    public $modalMode = 'create';

    public $formData = [];

    public $recordId = null;

    public function render()
    {
        $records = FormTemplate::when($this->search, fn ($q) => $q->where('name', 'like', '%' . $this->search . '%'))
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.admin.master.form-template-manager', compact('records'));
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
        $this->formData = ['is_active' => true, 'version' => '1.0'];
        $this->showModal = true;
    }

    public function openEditModal(int $id): void
    {
        $record = FormTemplate::findOrFail($id);
        $this->recordId = $id;
        $this->modalMode = 'edit';
        $this->formData = $record->only(['name', 'template_key', 'version', 'description', 'is_active']);
        $this->showModal = true;
    }

    protected function rules(): array
    {
        $uniqueRule = $this->modalMode === 'edit'
            ? 'required|string|max:100|unique:form_templates,template_key,' . $this->recordId
            : 'required|string|max:100|unique:form_templates,template_key';

        return [
            'formData.name' => 'required|string|max:255',
            'formData.template_key' => $uniqueRule,
            'formData.version' => 'required|string|max:20',
            'formData.description' => 'nullable|string|max:2000',
            'formData.is_active' => 'boolean',
        ];
    }

    public function save(): void
    {
        $this->validate();

        if ($this->modalMode === 'create') {
            FormTemplate::create($this->formData);
            flash()->success('Form template created successfully!');
        } else {
            FormTemplate::findOrFail($this->recordId)->update($this->formData);
            flash()->success('Form template updated successfully!');
        }

        $this->closeModal();
    }

    public function delete(int $id): void
    {
        $this->recordId = $id;
        sweetalert()->showDenyButton()->info('Are you sure you want to delete this form template?');
    }

    #[On('sweetalert:confirmed')]
    public function onConfirmed(array $payload): void
    {
        FormTemplate::findOrFail($this->recordId)->delete();
        $this->recordId = null;
        flash()->info('Form template successfully deleted.');
    }

    #[On('sweetalert:denied')]
    public function onDeny(array $payload): void
    {
        $this->recordId = null;
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
        $this->recordId = null;
        $this->resetValidation();
    }
}
