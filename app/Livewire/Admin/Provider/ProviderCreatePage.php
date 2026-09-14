<?php

namespace App\Livewire\Admin\Provider;

use App\Enums\ProviderCredentialType;
use App\Livewire\Concerns\LooksUpUsZip;
use App\Models\ProviderDetails;
use App\Models\Specialty;
use App\Models\User;
use App\Services\ProviderCredentialService;
use App\Support\UsStates;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::admin', ['title' => 'Add Provider'])]
class ProviderCreatePage extends Component
{
    use LooksUpUsZip;

    public $formData = [];

    public $userData = [
        'name' => '',
        'email' => '',
        'phone' => '',
        'password' => '',
    ];

    public $specialties = [];

    public $npiDuplicateWarning = '';

    public array $credentialRows = [];

    protected function rules(): array
    {
        return [
            'userData.name' => 'required|string|max:255',
            'userData.email' => 'required|email|unique:users,email',
            'userData.phone' => 'nullable|string|max:30',
            'userData.password' => 'required|string|min:6',
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
            'credentialRows.*.credential_type' => 'nullable|string',
            'credentialRows.*.state' => 'nullable|string|max:2',
            'credentialRows.*.number' => 'nullable|string|max:50',
            'credentialRows.*.expiry_date' => 'nullable|date',
            'formData.practice' => 'required|string|max:255',
            'formData.address' => 'required|string|max:500',
            'formData.city' => 'required|string|max:100',
            'formData.state' => ['required', 'string', 'size:2', Rule::in(UsStates::codes())],
            'formData.zip' => 'required|string|max:20',
            'formData.status' => 'required|in:pending,approved,rejected',
        ];
    }

    public function mount()
    {
        $this->specialties = Specialty::all();
        $this->credentialRows = [$this->emptyCredentialRow(true)];
    }

    public function addCredentialRow(): void
    {
        $this->credentialRows[] = $this->emptyCredentialRow(false);
    }

    public function removeCredentialRow(int $index): void
    {
        unset($this->credentialRows[$index]);
        $this->credentialRows = array_values($this->credentialRows);
        if ($this->credentialRows === []) {
            $this->credentialRows = [$this->emptyCredentialRow(true)];
        }
    }

    public function updatedFormDataNpi($value): void
    {
        if ($value && ProviderDetails::where('npi', $value)->exists()) {
            $this->npiDuplicateWarning = 'Warning: A provider with this NPI already exists in the system.';
        } else {
            $this->npiDuplicateWarning = '';
        }
    }

    public function save()
    {
        $canManagePortalCredentials = Auth::guard('admin')->user()?->can('admin.portal-credentials.manage') ?? false;

        $rules = $this->rules();

        if (! $canManagePortalCredentials) {
            $rules['userData.password'] = 'nullable|string|min:6';
        }

        $this->validate($rules);

        $password = $canManagePortalCredentials && filled($this->userData['password'])
            ? $this->userData['password']
            : Str::password(16);

        // Create user
        $user = User::create([
            'name' => $this->userData['name'],
            'email' => $this->userData['email'],
            'phone' => $this->userData['phone'] ?? null,
            'password' => $password,
        ]);

        // Assign provider role
        if (method_exists($user, 'assignRole')) {
            $user->assignRole('provider');
        }

        $providerData = $this->formData;
        $providerData['user_id'] = $user->id;
        $provider = ProviderDetails::create($this->normalizeMalpracticeFields($providerData));

        $this->persistCredentialRows($provider);

        flash()->success('Provider and user created successfully!');

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

    protected function persistCredentialRows(ProviderDetails $provider): void
    {
        $service = app(ProviderCredentialService::class);
        $savedPrimary = false;

        foreach ($this->credentialRows as $row) {
            $number = trim((string) ($row['number'] ?? ''));
            $state = UsStates::normalize($row['state'] ?? null);
            $type = $row['credential_type'] ?? ProviderCredentialType::License->value;

            if ($number === '' || $state === null) {
                continue;
            }

            $service->save($provider, [
                'credential_type' => $type,
                'state' => $state,
                'number' => $number,
                'expiry_date' => $row['expiry_date'] ?? null,
                'is_primary' => ! $savedPrimary && $type === ProviderCredentialType::License->value,
            ]);

            if ($type === ProviderCredentialType::License->value) {
                $savedPrimary = true;
            }
        }
    }

    protected function emptyCredentialRow(bool $primary): array
    {
        return [
            'credential_type' => ProviderCredentialType::License->value,
            'state' => '',
            'number' => '',
            'expiry_date' => '',
            'is_primary' => $primary,
        ];
    }

    public function render()
    {
        $users = User::whereDoesntHave('providerDetails')->get();

        return view('livewire.admin.provider.provider-create-page', [
            'users' => $users,
            'states' => UsStates::all(),
            'credentialTypes' => ProviderCredentialType::cases(),
        ]);
    }
}
