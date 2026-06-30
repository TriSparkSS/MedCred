<?php

namespace App\Livewire\Admin\Provider;

use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Models\ProviderDetails;
use App\Models\Specialty;
use App\Models\User;

#[Layout('layouts::admin', ['title' => 'Add Provider'])]
class ProviderCreatePage extends Component
{
    public $formData = [];
    public $userData = [
        'name' => '',
        'email' => '',
        'phone' => '',
        'password' => '',
    ];
    public $specialties = [];

    public $licensedStatesInput = '';

    protected $rules = [
        'userData.name' => 'required|string|max:255',
        'userData.email' => 'required|email|unique:users,email',
        'userData.phone' => 'nullable|string|max:30',
        'userData.password' => 'required|string|min:6',
        'formData.specialty_id' => 'nullable|exists:specialties,id',
        'formData.npi' => 'required|string|max:50|unique:provider_details,npi',
        'formData.caqh_id' => 'nullable|numeric|digits_between:1,15',
        'formData.license_number' => 'nullable|string|max:50|regex:/^[A-Za-z0-9\-]+$/',
        'formData.license_state' => 'nullable|string|max:50',
        'formData.dea' => 'nullable|string|max:20|regex:/^[A-Za-z0-9]+$/',
        'formData.taxonomy_code' => 'nullable|string|max:50',
        'formData.pecos_id' => 'nullable|string|max:50',
        'formData.pecos_enrolled' => 'boolean',
        'formData.malpractice_carrier' => 'nullable|string|max:255',
        'formData.malpractice_policy_number' => 'nullable|string|max:100',
        'formData.malpractice_expiry' => 'nullable|date',
        'formData.board_certification' => 'nullable|string|max:255',
        'formData.board_cert_expiry' => 'nullable|date',
        'formData.cds_number' => 'nullable|string|max:50',
        'formData.cds_state' => 'nullable|string|max:50',
        'formData.work_history' => 'nullable|string|max:5000',
        'licensedStatesInput' => 'nullable|string|max:500',
        'formData.practice' => 'required|string|max:255',
        'formData.address' => 'required|string|max:500',
        'formData.city' => 'required|string|max:100',
        'formData.state' => 'required|string|max:50',
        'formData.zip' => 'required|string|max:20',
        'formData.status' => 'required|in:pending,approved,rejected',
    ];

    public function mount()
    {
        $this->specialties = Specialty::all();
    }

    public function save()
    {
        $this->validate();

        // Create user
        $user = User::create([
            'name' => $this->userData['name'],
            'email' => $this->userData['email'],
            'phone' => $this->userData['phone'] ?? null,
            'password' => $this->userData['password'],
        ]);

        // Assign provider role
        if (method_exists($user, 'assignRole')) {
            $user->assignRole('provider');
        }

        // Create provider details
        $providerData = $this->formData;
        $providerData['user_id'] = $user->id;
        $providerData['licensed_states'] = $this->parseLicensedStates();
        ProviderDetails::create($providerData);

        flash()->success('Provider and user created successfully!');

        return redirect()->route('admin.providers');
    }

    protected function parseLicensedStates(): ?array
    {
        if (blank($this->licensedStatesInput)) {
            return null;
        }

        return array_values(array_filter(array_map('trim', explode(',', $this->licensedStatesInput))));
    }

    public function render()
    {
        $users = User::whereDoesntHave('providerDetails')->get();
        return view('livewire.admin.provider.provider-create-page', compact('users'));
    }
}
