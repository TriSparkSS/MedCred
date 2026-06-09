<?php

namespace App\Livewire\Admin\Provider;

use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Models\ProviderDetails;
use App\Models\User;
use App\Models\Specialty;

#[Layout('layouts::admin', ['title' => 'Edit Provider'])]
class ProviderEditPage extends Component
{
    public $providerId;
    public $formData = [];
    public $userData = [];
    public $specialties = [];

    protected $rules = [
        'userData.name' => 'required|string|max:255',
        'userData.email' => 'required|email',
        'userData.phone' => 'nullable|string|max:30',
        'formData.specialty_id' => 'nullable|exists:specialties,id',
        'formData.npi' => 'required|string|max:50',
        'formData.practice' => 'required|string|max:255',
        'formData.address' => 'required|string|max:500',
        'formData.city' => 'required|string|max:100',
        'formData.state' => 'required|string|max:50',
        'formData.zip' => 'required|string|max:20',
        'formData.status' => 'required|string|max:50',
    ];

    public function mount($provider)
    {
        $this->providerId = $provider;
        $this->specialties = Specialty::all();

        $providerModel = ProviderDetails::with('user')->findOrFail($this->providerId);

        $this->formData = [
            'specialty_id' => $providerModel->specialty_id,
            'npi' => $providerModel->npi,
            'practice' => $providerModel->practice,
            'address' => $providerModel->address,
            'city' => $providerModel->city,
            'state' => $providerModel->state,
            'zip' => $providerModel->zip,
            'status' => $providerModel->status,
        ];

        $this->userData = [
            'name' => $providerModel->user->name ?? '',
            'email' => $providerModel->user->email ?? '',
            'phone' => $providerModel->user->phone ?? '',
        ];
    }

    public function save()
    {
        // Ensure NPI is unique except for current provider
        $this->rules['formData.npi'] = 'required|string|max:50|unique:provider_details,npi,' . $this->providerId;
        $this->validate();

        $provider = ProviderDetails::findOrFail($this->providerId);

        // update user
        $user = User::findOrFail($provider->user_id);
        $user->update([
            'name' => $this->userData['name'],
            'email' => $this->userData['email'],
            'phone' => $this->userData['phone'] ?? null,
        ]);

        // update provider
        $provider->update($this->formData);

        flash()->success('Provider updated successfully!');

        return redirect()->route('admin.providers');
    }

    public function render()
    {
        return view('livewire.admin.provider.provider-edit-page');
    }
}
