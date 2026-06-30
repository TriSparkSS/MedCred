<?php

namespace App\Livewire\Admin\Master;

use App\Models\NotificationTemplate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::admin', ['title' => 'Email Templates'])]
class NotificationTemplateManager extends Component
{
    use WithPagination;

    public $search = '';

    public $showModal = false;

    public $modalMode = 'create';

    public $formData = [];

    protected function rules(): array
    {
        return [
            'formData.name' => 'required|string|max:255',
            'formData.template_key' => 'required|string|max:100',
            'formData.subject' => 'required|string|max:255',
            'formData.body' => 'required|string|max:10000',
            'formData.category' => 'required|string|max:50',
            'formData.is_active' => 'boolean',
        ];
    }

    public function openCreateModal(): void
    {
        $this->modalMode = 'create';
        $this->formData = ['is_active' => true, 'category' => 'general'];
        $this->showModal = true;
    }

    public function openEditModal(int $id): void
    {
        $record = NotificationTemplate::findOrFail($id);
        $this->modalMode = 'edit';
        $this->formData = $record->toArray();
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate();

        if ($this->modalMode === 'create') {
            NotificationTemplate::create($this->formData);
            flash()->success('Template created.');
        } else {
            NotificationTemplate::findOrFail($this->formData['id'])->update($this->formData);
            flash()->success('Template updated.');
        }

        $this->showModal = false;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
    }

    public function render()
    {
        $records = NotificationTemplate::when($this->search, fn ($q) => $q->where('name', 'like', '%' . $this->search . '%'))
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.admin.master.notification-template-manager', compact('records'));
    }
}
