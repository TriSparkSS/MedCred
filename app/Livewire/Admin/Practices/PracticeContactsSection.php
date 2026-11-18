<?php

namespace App\Livewire\Admin\Practices;

use App\Models\PracticeContact;
use App\Services\AdminScopeService;
use App\Services\PracticeContactService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

class PracticeContactsSection extends Component
{
    #[Locked]
    public $practiceId;

    #[Locked]
    public bool $canManage = false;

    public $showModal = false;

    public $modalMode = 'create';

    #[Locked]
    public $contactId = null;

    public $formData = [];

    public function mount(): void
    {
        $this->authorizePractice();
        $this->canManage = Auth::guard('admin')->user()?->can('admin.practices.manage') ?? false;
    }

    protected function authorizePractice(): void
    {
        $admin = Auth::guard('admin')->user();
        if ($admin && $this->practiceId && ! app(AdminScopeService::class)->canAccessPractice($admin, (int) $this->practiceId)) {
            abort(403);
        }
    }

    protected function rules(): array
    {
        return PracticeContactService::rules('formData');
    }

    public function openCreateModal(): void
    {
        $this->canManage = (bool) Auth::guard('admin')->user()?->can('admin.practices.manage');
        abort_unless($this->canManage, 403);
        $this->authorizePractice();

        $this->resetForm();
        $this->modalMode = 'create';
        $this->formData = ['is_primary' => false];
        $this->showModal = true;
    }

    public function openEditModal(int $id): void
    {
        $this->canManage = (bool) Auth::guard('admin')->user()?->can('admin.practices.manage');
        abort_unless($this->canManage, 403);
        $this->authorizePractice();

        $contact = PracticeContact::where('practice_id', $this->practiceId)->findOrFail($id);
        $this->contactId = $id;
        $this->modalMode = 'edit';
        $this->formData = $contact->only(['name', 'title', 'email', 'phone', 'fax', 'is_primary', 'notes']);
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->canManage = (bool) Auth::guard('admin')->user()?->can('admin.practices.manage');
        abort_unless($this->canManage, 403);
        $this->authorizePractice();

        $this->validate();

        $service = app(PracticeContactService::class);

        if ($this->modalMode === 'create') {
            $service->create((int) $this->practiceId, $this->formData);
            flash()->success('Contact added successfully!');
        } else {
            $service->update(
                PracticeContact::where('practice_id', $this->practiceId)->findOrFail($this->contactId),
                $this->formData
            );
            flash()->success('Contact updated successfully!');
        }

        $this->closeModal();
    }

    public function delete(int $id): void
    {
        $this->canManage = (bool) Auth::guard('admin')->user()?->can('admin.practices.manage');
        abort_unless($this->canManage, 403);
        $this->authorizePractice();

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
        $this->authorizePractice();

        $contacts = PracticeContact::where('practice_id', $this->practiceId)->orderByDesc('is_primary')->orderBy('name')->get();

        return view('livewire.admin.practices.partials.practice-contacts-section', compact('contacts'));
    }
}
