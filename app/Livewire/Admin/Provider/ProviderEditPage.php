<?php

namespace App\Livewire\Admin\Provider;

use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Models\ProviderDetails;
use App\Models\User;
use App\Models\Specialty;
use Illuminate\Support\Facades\Auth;

#[Layout('layouts::admin', ['title' => 'Edit Provider'])]
class ProviderEditPage extends Component
{
    public $providerId;
    public $formData = [];
    public $userData = [];
    public $specialties = [];

    public $licensedStatesInput = '';

    protected $rules = [
        'userData.name' => 'required|string|max:255',
        'userData.email' => 'required|email',
        'userData.phone' => 'nullable|string|max:30',
        'userData.password' => 'nullable|string|min:6',
        'formData.specialty_id' => 'nullable|exists:specialties,id',
        'formData.npi' => 'required|string|max:50',
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

    public function mount($provider)
    {
        $this->providerId = $provider;
        $this->specialties = Specialty::all();

        $providerModel = ProviderDetails::with('user')->findOrFail($this->providerId);
        $admin = Auth::guard('admin')->user();
        if ($admin && ! app(\App\Services\AdminScopeService::class)->canAccessProvider($admin, $providerModel)) {
            abort(403, 'You do not have access to this provider.');
        }

        $this->formData = [
            'specialty_id' => $providerModel->specialty_id,
            'npi' => $providerModel->npi,
            'caqh_id' => $providerModel->caqh_id,
            'license_number' => $providerModel->license_number,
            'license_state' => $providerModel->license_state,
            'dea' => $providerModel->dea,
            'taxonomy_code' => $providerModel->taxonomy_code,
            'pecos_id' => $providerModel->pecos_id,
            'pecos_enrolled' => $providerModel->pecos_enrolled,
            'malpractice_carrier' => $providerModel->malpractice_carrier,
            'malpractice_policy_number' => $providerModel->malpractice_policy_number,
            'malpractice_expiry' => $providerModel->malpractice_expiry?->format('Y-m-d'),
            'board_certification' => $providerModel->board_certification,
            'board_cert_expiry' => $providerModel->board_cert_expiry?->format('Y-m-d'),
            'cds_number' => $providerModel->cds_number,
            'cds_state' => $providerModel->cds_state,
            'work_history' => $providerModel->work_history,
            'practice' => $providerModel->practice,
            'address' => $providerModel->address,
            'city' => $providerModel->city,
            'state' => $providerModel->state,
            'zip' => $providerModel->zip,
            'status' => $providerModel->status,
        ];

        $this->licensedStatesInput = is_array($providerModel->licensed_states)
            ? implode(', ', $providerModel->licensed_states)
            : '';

        $this->userData = [
            'name' => $providerModel->user->name ?? '',
            'email' => $providerModel->user->email ?? '',
            'phone' => $providerModel->user->phone ?? '',
            'password' => '',
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
        $userPayload = [
            'name' => $this->userData['name'],
            'email' => $this->userData['email'],
            'phone' => $this->userData['phone'] ?? null,
        ];

        if (filled($this->userData['password'])) {
            if (Auth::guard('admin')->user()?->can('admin.portal-credentials.manage')) {
                $userPayload['password'] = $this->userData['password'];
            }
        }

        $user->update($userPayload);
        $this->userData['password'] = '';

        // update provider
        $updateData = $this->formData;
        $updateData['licensed_states'] = $this->parseLicensedStates();
        $provider->update($updateData);

        flash()->success('Provider updated successfully!');

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
        return view('livewire.admin.provider.provider-edit-page');
    }
}
