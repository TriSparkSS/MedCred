<?php

namespace App\Livewire\Admin\Practices;

use App\Models\PracticeContact;
use Livewire\Component;

class PracticeContactsSection extends Component
{
    public $practiceId;

    public $showModal = false;

    public $modalMode = 'create';

    public $contactId = null;

    public $formData = [];

    protected function rules(): array
    {
        return [
            'formData.name' => 'required|string|max:255',
            'formData.title' => 'nullable|string|max:255',
            'formData.email' => 'nullable|email|max:255',
            'formData.phone' => 'nullable|string|max:30',
            'formData.fax' => 'nullable|string|max:30',
            'formData.is_primary' => 'boolean',
            'formData.notes' => 'nullable|string|max:1000',
        ];
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->modalMode = 'create';
        $this->formData = ['is_primary' => false];
        $this->showModal = true;
    }

    public function openEditModal(int $id): void
    {
        $contact = PracticeContact::where('practice_id', $this->practiceId)->findOrFail($id);
        $this->contactId = $id;
        $this->modalMode = 'edit';
        $this->formData = $contact->only(['name', 'title', 'email', 'phone', 'fax', 'is_primary', 'notes']);
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate();

        if ($this->formData['is_primary'] ?? false) {
            PracticeContact::where('practice_id', $this->practiceId)->update(['is_primary' => false]);
        }

        if ($this->modalMode === 'create') {
            PracticeContact::create([...$this->formData, 'practice_id' => $this->practiceId]);
            flash()->success('Contact added successfully!');
        } else {
            PracticeContact::where('practice_id', $this->practiceId)->findOrFail($this->contactId)->update($this->formData);
            flash()->success('Contact updated successfully!');
        }

        $this->closeModal();
    }

    public function delete(int $id): void
    {
        PracticeContact::where('practice_id', $this->practiceId)->findOrFail($id)->delete();
        flash()->info('Contact deleted.');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->formData = [];
        $this->contactId = null;
        $this->resetValidation();
    }

    public function render()
    {
        $contacts = PracticeContact::where('practice_id', $this->practiceId)->orderByDesc('is_primary')->orderBy('name')->get();

        return view('livewire.admin.practices.partials.practice-contacts-section', compact('contacts'));
    }
}
