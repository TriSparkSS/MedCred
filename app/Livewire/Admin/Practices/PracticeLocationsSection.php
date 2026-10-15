<?php

namespace App\Livewire\Admin\Practices;

use App\Livewire\Concerns\LooksUpUsZip;
use App\Models\Location;
use App\Services\AdminScopeService;
use App\Support\UsStates;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

class PracticeLocationsSection extends Component
{
    use LooksUpUsZip;

    #[Locked]
    public $practiceId;

    #[Locked]
    public bool $canManage = false;

    public $showModal = false;

    public $modalMode = 'create';

    #[Locked]
    public $locationId = null;

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
        return [
            'formData.name' => 'required|string|max:255',
            'formData.address1' => 'required|string|max:255',
            'formData.address2' => 'nullable|string|max:255',
            'formData.city' => 'required|string|max:100',
            'formData.state' => ['required', 'string', 'size:2', Rule::in(UsStates::codes())],
            'formData.zip_code' => 'required|string|max:20',
            'formData.county' => 'nullable|string|max:100',
            'formData.country' => 'required|string|max:100',
            'formData.phone' => 'nullable|string|max:30',
            'formData.fax' => 'nullable|string|max:30',
            'formData.taxonomy_code' => 'nullable|string|max:50',
            'formData.npi' => 'nullable|string|max:50',
            'formData.status' => 'required|in:active,inactive',
            'formData.is_primary' => 'boolean',
        ];
    }

    public function openCreateModal(): void
    {
        $this->canManage = (bool) Auth::guard('admin')->user()?->can('admin.practices.manage');
        abort_unless($this->canManage, 403);
        $this->authorizePractice();

        $this->resetForm();
        $this->modalMode = 'create';
        $this->formData = [
            'country' => 'United States',
            'status' => 'active',
            'is_primary' => false,
            'city' => '',
            'state' => '',
            'zip_code' => '',
            'county' => '',
        ];
        $this->showModal = true;
    }

    public function openEditModal(int $id): void
    {
        $this->canManage = (bool) Auth::guard('admin')->user()?->can('admin.practices.manage');
        abort_unless($this->canManage, 403);
        $this->authorizePractice();

        $location = Location::where('practice_id', $this->practiceId)->findOrFail($id);
        $this->locationId = $id;
        $this->modalMode = 'edit';
        $this->formData = $location->only([
            'name', 'address1', 'address2', 'city', 'state', 'zip_code', 'county',
            'country', 'phone', 'fax', 'taxonomy_code', 'npi', 'status', 'is_primary',
        ]);
        $this->formData['state'] = UsStates::normalize($this->formData['state'] ?? null) ?? ($this->formData['state'] ?? '');
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->canManage = (bool) Auth::guard('admin')->user()?->can('admin.practices.manage');
        abort_unless($this->canManage, 403);
        $this->authorizePractice();

        $this->validate();

        if ($this->formData['is_primary'] ?? false) {
            Location::where('practice_id', $this->practiceId)->update(['is_primary' => false]);
        }

        $payload = $this->formData;
        $payload['state'] = UsStates::normalize($payload['state'] ?? null) ?? $payload['state'];

        if ($this->modalMode === 'create') {
            Location::create([...$payload, 'practice_id' => $this->practiceId]);
            flash()->success('Location added successfully!');
        } else {
            Location::where('practice_id', $this->practiceId)->findOrFail($this->locationId)->update($payload);
            flash()->success('Location updated successfully!');
        }

        $this->closeModal();
    }

    public function delete(int $id): void
    {
        $this->canManage = (bool) Auth::guard('admin')->user()?->can('admin.practices.manage');
        abort_unless($this->canManage, 403);
        $this->authorizePractice();

        Location::where('practice_id', $this->practiceId)->findOrFail($id)->delete();
        flash()->info('Location deleted.');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->formData = [];
        $this->locationId = null;
        $this->zipLookupMessages = [];
        $this->resetValidation();
    }

    public function render()
    {
        $this->authorizePractice();

        $locations = Location::where('practice_id', $this->practiceId)->orderByDesc('is_primary')->orderBy('name')->get();

        return view('livewire.admin.practices.partials.practice-locations-section', compact('locations'));
    }
}
