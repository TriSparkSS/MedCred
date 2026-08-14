<?php

namespace App\Livewire\Admin\Master;

use App\Enums\AdminRole;
use App\Models\NotificationRule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::admin', ['title' => 'Notification Rules | Settings'])]
class NotificationRuleManager extends Component
{
    use WithPagination;

    public $search = '';

    public $showModal = false;

    public $modalMode = 'create';

    public $formData = [];

    public $recordId = null;

    public function render()
    {
        $records = NotificationRule::when($this->search, fn ($q) => $q->where('name', 'like', '%' . $this->search . '%'))
            ->orderBy('name')
            ->paginate(15);

        $channels = ['database' => 'In-App', 'mail' => 'Email'];
        $adminRoles = AdminRole::cases();

        return view('livewire.admin.master.notification-rule-manager', compact('records', 'channels', 'adminRoles'));
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
        $this->formData = ['is_active' => true, 'channel' => 'database', 'recipient_roles' => []];
        $this->showModal = true;
    }

    public function openEditModal(int $id): void
    {
        $record = NotificationRule::findOrFail($id);
        $this->recordId = $id;
        $this->modalMode = 'edit';
        $this->formData = $record->only(['name', 'event_key', 'channel', 'recipient_roles', 'is_active']);
        $this->formData['recipient_roles'] = $record->recipient_roles ?? [];
        $this->showModal = true;
    }

    protected function rules(): array
    {
        return [
            'formData.name' => 'required|string|max:255',
            'formData.event_key' => 'required|string|max:100',
            'formData.channel' => 'required|string|max:50',
            'formData.recipient_roles' => 'nullable|array',
            'formData.recipient_roles.*' => 'string|max:100',
            'formData.is_active' => 'boolean',
        ];
    }

    public function save(): void
    {
        $this->validate();

        if ($this->modalMode === 'create') {
            NotificationRule::create($this->formData);
            flash()->success('Notification rule created successfully!');
        } else {
            NotificationRule::findOrFail($this->recordId)->update($this->formData);
            flash()->success('Notification rule updated successfully!');
        }

        $this->closeModal();
    }

    public function delete(int $id): void
    {
        $this->recordId = $id;
        sweetalert()->showDenyButton()->info('Are you sure you want to delete this notification rule?');
    }

    #[On('sweetalert:confirmed')]
    public function onConfirmed(array $payload): void
    {
        NotificationRule::findOrFail($this->recordId)->delete();
        $this->recordId = null;
        flash()->info('Notification rule successfully deleted.');
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
