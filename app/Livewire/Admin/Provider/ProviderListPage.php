<?php

namespace App\Livewire\Admin\Provider;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Livewire\Attributes\On;
use App\Models\ProviderDetails;
use App\Models\Specialty;
use App\Models\User;
use App\Enums\ProviderStatus;

#[Layout('layouts::admin', ['title' => 'Providers'])]
class ProviderListPage extends Component
{
    use WithPagination;

    public $search = '';
    public $formData = [];
    public $providerId = null;
    public $specialties = [];
    public $filterSpecialty = '';
    public $filterStatus = '';

    protected $rules = [
        'formData.user_id' => 'required|exists:users,id',
        'formData.specialty_id' => 'nullable|exists:specialties,id',
        'formData.npi' => 'required|string|max:50|unique:provider_details,npi',
        'formData.practice' => 'required|string|max:255',
        'formData.address' => 'required|string|max:500',
        'formData.city' => 'required|string|max:100',
        'formData.state' => 'required|string|max:50',
        'formData.zip' => 'required|string|max:20',
        'formData.status' => 'required|string|max:50',
    ];

    public function mount()
    {
        $this->specialties = Specialty::all();
    }

    public function updated($propertyName)
    {
        if (in_array($propertyName, ['search', 'filterSpecialty', 'filterStatus'])) {
            $this->resetPage();
        }
    }

    public function openCreateModal()
    {
        // Creation moved to a separate page. Use the create page instead of modal.
        return redirect()->route('admin.providers.create');
    }

    public function openEditModal($providerId)
    {
        return redirect()->route('admin.providers.edit', $providerId);
    }

    // Editing moved to separate page; listing only handles navigation and deletion.

    public function delete(int $id): void
    {
        $this->providerId = $id;
        sweetalert()
            ->showDenyButton()
            ->info('Are you sure you want to delete this provider?');
    }

    #[On('sweetalert:confirmed')]
    public function onConfirmed(array $payload): void
    {
        $provider = ProviderDetails::findOrFail($this->providerId);
        $provider->delete();
        $this->providerId = null;
        flash()->info('Provider successfully deleted.');
    }

    #[On('sweetalert:denied')]
    public function onDeny(array $payload): void
    {
        $this->providerId = null;
        flash()->info('Deletion cancelled.');
    }

    public function closeModal()
    {
        // No modal on listing; kept for compatibility but does nothing.
    }

    private function resetForm()
    {
        $this->formData = [];
        $this->providerId = null;
        $this->resetValidation();
    }

    public function render()
    {
        $query = ProviderDetails::with('user', 'specialty');

        if ($this->filterSpecialty) {
            $query->where('specialty_id', $this->filterSpecialty);
        }

        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
        }

        if ($this->search) {
            $query->whereHas('user', function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%');
            })->whereHas('specialty', function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%');
            })->orWhere('npi', 'like', '%' . $this->search . '%')
                ->orWhere('practice', 'like', '%' . $this->search . '%')
                ->orWhere('npi', 'like', '%' . $this->search . '%')
                ->orWhere('city', 'like', '%' . $this->search . '%');
        }

        $query->orderBy('created_at', 'desc');
        $providers = $query->paginate(10);

        $stats = [
            'total' => ProviderDetails::count(),
            'active' => ProviderDetails::where('status', ProviderStatus::APPROVED->value)->count(),
            'pending' => ProviderDetails::where('status', ProviderStatus::PENDING->value)->count(),
        ];

        $specialties = Specialty::orderBy('name')->get();
        return view('livewire.admin.provider.provider-list-page', compact('providers', 'stats', 'specialties'));
    }
}
