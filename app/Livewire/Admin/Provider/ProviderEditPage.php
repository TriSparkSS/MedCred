<?php

namespace App\Livewire\Admin\Provider;

use App\Enums\ProviderStatus;
use App\Livewire\Concerns\LooksUpUsZip;
use App\Models\ProviderDetails;
use App\Models\Specialty;
use App\Models\User;
use App\Services\AdminScopeService;
use App\Support\UsStates;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::admin', ['title' => 'Edit Provider'])]
class ProviderEditPage extends Component
{
    use LooksUpUsZip;

    public $providerId;

    public $formData = [];

    public $userData = [];

    public $specialties = [];

    protected function rules(): array
    {
        return [
            'userData.name' => 'required|string|max:255',
            'userData.email' => 'required|email',
            'userData.phone' => 'nullable|string|max:30',
            'userData.password' => 'nullable|string|min:6',
            'formData.specialty_id' => 'nullable|exists:specialties,id',
            'formData.npi' => 'required|string|max:50',
            'formData.caqh_id' => 'nullable|numeric|digits_between:1,15',
            'formData.taxonomy_code' => 'nullable|string|max:50',
            'formData.pecos_id' => 'nullable|string|max:50',
            'formData.pecos_enrolled' => 'boolean',
            'formData.malpractice_carrier' => 'nullable|string|max:255',
            'formData.malpractice_policy_number' => 'nullable|string|max:100',
            'formData.malpractice_coverage_each_occurrence' => 'nullable|numeric|min:0',
            'formData.malpractice_coverage_aggregate' => 'nullable|numeric|min:0',
            'formData.malpractice_effective_date' => array_values(array_filter([
                'nullable',
                'date',
                filled($this->formData['malpractice_expiry'] ?? null) ? 'before_or_equal:formData.malpractice_expiry' : null,
            ])),
            'formData.malpractice_expiry' => 'nullable|date',
            'formData.board_certification' => 'nullable|string|max:255',
            'formData.board_cert_expiry' => 'nullable|date',
            'formData.work_history' => 'nullable|string|max:5000',
            'formData.practice' => 'required|string|max:255',
            'formData.address' => 'required|string|max:500',
            'formData.city' => 'required|string|max:100',
            'formData.state' => ['required', 'string', 'size:2', Rule::in(UsStates::codes())],
            'formData.zip' => 'required|string|max:20',
            'formData.status' => 'required|in:pending,approved,rejected',
        ];
    }

    public function mount($provider)
    {
        $this->providerId = $provider;
        $this->specialties = Specialty::all();

        $providerModel = ProviderDetails::with('user')->findOrFail($this->providerId);
        $admin = Auth::guard('admin')->user();
        if ($admin && ! app(AdminScopeService::class)->canAccessProvider($admin, $providerModel)) {
            abort(403, 'You do not have access to this provider.');
        }

        $this->formData = [
            'specialty_id' => $providerModel->specialty_id,
            'npi' => $providerModel->npi,
            'caqh_id' => $providerModel->caqh_id,
            'taxonomy_code' => $providerModel->taxonomy_code,
            'pecos_id' => $providerModel->pecos_id,
            'pecos_enrolled' => $providerModel->pecos_enrolled,
            'malpractice_carrier' => $providerModel->malpractice_carrier,
            'malpractice_policy_number' => $providerModel->malpractice_policy_number,
            'malpractice_coverage_each_occurrence' => $providerModel->malpractice_coverage_each_occurrence,
            'malpractice_coverage_aggregate' => $providerModel->malpractice_coverage_aggregate,
            'malpractice_effective_date' => $providerModel->malpractice_effective_date?->format('Y-m-d'),
            'malpractice_expiry' => $providerModel->malpractice_expiry?->format('Y-m-d'),
            'board_certification' => $providerModel->board_certification,
            'board_cert_expiry' => $providerModel->board_cert_expiry?->format('Y-m-d'),
            'work_history' => $providerModel->work_history,
            'practice' => $providerModel->practice,
            'address' => $providerModel->address,
            'city' => $providerModel->city,
            'state' => UsStates::normalize($providerModel->state) ?? $providerModel->state,
            'zip' => $providerModel->zip,
            'status' => $providerModel->status instanceof ProviderStatus
                ? $providerModel->status->value
                : $providerModel->status,
        ];

        $this->userData = [
            'name' => $providerModel->user->name ?? '',
            'email' => $providerModel->user->email ?? '',
            'phone' => $providerModel->user->phone ?? '',
            'password' => '',
        ];
    }

    public function save()
    {
        $rules = $this->rules();
        $rules['formData.npi'] = 'required|string|max:50|unique:provider_details,npi,'.$this->providerId;
        $this->validate($rules);

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

        $provider->update($this->normalizeMalpracticeFields($this->formData));

        flash()->success('Provider updated successfully!');

        return redirect()->route('admin.providers.show', $provider->id);
    }

    private function normalizeMalpracticeFields(array $data): array
    {
        foreach (['malpractice_coverage_each_occurrence', 'malpractice_coverage_aggregate', 'malpractice_effective_date'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] === '') {
                $data[$field] = null;
            }
        }

        return $data;
    }

    public function render()
    {
        return view('livewire.admin.provider.provider-edit-page');
    }
}
