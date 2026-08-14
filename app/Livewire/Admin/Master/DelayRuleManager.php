<?php

namespace App\Livewire\Admin\Master;

use App\Models\DelayOwner;
use App\Models\DelayRule;
use App\Models\Status;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::admin', ['title' => 'Delay Rules | Settings'])]
class DelayRuleManager extends Component
{
    use WithPagination;

    public $search = '';

    public $showModal = false;

    public $modalMode = 'create';

    public $formData = [];

    public $recordId = null;

    public function render()
    {
        $records = DelayRule::with(['status', 'delayOwner'])
            ->when($this->search, fn ($q) => $q->where('name', 'like', '%' . $this->search . '%'))
            ->orderBy('name')
            ->paginate(15);

        $statuses = Status::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
        $delayOwners = DelayOwner::where('is_active', true)->orderBy('name')->get();

        return view('livewire.admin.master.delay-rule-manager', compact('records', 'statuses', 'delayOwners'));
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
        $this->formData = ['is_active' => true];
        $this->showModal = true;
    }

    public function openEditModal(int $id): void
    {
        $record = DelayRule::findOrFail($id);
        $this->recordId = $id;
        $this->modalMode = 'edit';
        $this->formData = $record->only(['name', 'status_id', 'event_key', 'delay_owner_id', 'is_active']);
        $this->showModal = true;
    }

    protected function rules(): array
    {
        return [
            'formData.name' => 'required|string|max:255',
            'formData.status_id' => 'nullable|exists:statuses,id',
            'formData.event_key' => 'nullable|string|max:100',
            'formData.delay_owner_id' => 'required|exists:delay_owners,id',
            'formData.is_active' => 'boolean',
        ];
    }

    public function save(): void
    {
        $this->validate();

        if ($this->modalMode === 'create') {
            DelayRule::create($this->formData);
            flash()->success('Delay rule created successfully!');
        } else {
            DelayRule::findOrFail($this->recordId)->update($this->formData);
            flash()->success('Delay rule updated successfully!');
        }

        $this->closeModal();
    }

    public function delete(int $id): void
    {
        $this->recordId = $id;
        sweetalert()->showDenyButton()->info('Are you sure you want to delete this delay rule?');
    }

    #[On('sweetalert:confirmed')]
    public function onConfirmed(array $payload): void
    {
        DelayRule::findOrFail($this->recordId)->delete();
        $this->recordId = null;
        flash()->info('Delay rule successfully deleted.');
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
