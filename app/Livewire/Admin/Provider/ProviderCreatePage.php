<?php

namespace App\Livewire\Admin\Provider;

use App\Enums\ProviderCredentialType;
use App\Enums\ProviderStatus;
use App\Livewire\Admin\Provider\Concerns\ManagesProviderCredentialRows;
use App\Livewire\Admin\Provider\Concerns\SelectsPracticeAddress;
use App\Livewire\Concerns\LooksUpUsZip;
use App\Models\Practice;
use App\Models\ProviderDetails;
use App\Models\Specialty;
use App\Models\User;
use App\Services\AdminScopeService;
use App\Support\UsStates;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::admin', ['title' => 'Add Provider'])]
class ProviderCreatePage extends Component
{
    use LooksUpUsZip, ManagesProviderCredentialRows, SelectsPracticeAddress;

    public $formData = [];

    public $userData = [
        'name' => '',
        'email' => '',
        'phone' => '',
        'password' => '',
    ];

    public $specialties = [];

    public string $specialtySearch = '';

    public $npiDuplicateWarning = '';

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
            'practiceId' => 'required|exists:practices,id',
            'addressKey' => 'nullable|string',
            'formData.practice' => 'required|string|max:255',
            'formData.address' => 'required|string|max:500',
            'formData.city' => 'required|string|max:100',
            'formData.state' => ['required', 'string', 'size:2', Rule::in(UsStates::codes())],
            'formData.zip' => 'required|string|max:20',
            'formData.status' => ['required', Rule::enum(ProviderStatus::class)],
            'formData.status_remarks' => [
                Rule::requiredIf(fn () => ($this->formData['status'] ?? null) === ProviderStatus::ON_HOLD->value),
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }

    public function mount()
    {
        $this->specialties = Specialty::orderBy('name')->get();
        $this->credentialRows = [$this->emptyCredentialRow(true)];
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
        $this->assertPracticeInScope((int) $this->practiceId);

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
        unset($providerData['practice_id']);
        $providerData['user_id'] = $user->id;
        $provider = ProviderDetails::create($this->normalizeMalpracticeFields($providerData));

        $provider->practices()->syncWithoutDetaching([
            (int) $this->practiceId => ['primary_flag' => true],
        ]);

        $this->persistCredentialRows($provider, false);

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

    protected function filteredSpecialtyOptions()
    {
        $term = mb_strtolower(trim($this->specialtySearch));
        $selected = (string) ($this->formData['specialty_id'] ?? '');
        $items = collect($this->specialties);

        if ($term === '') {
            return $items;
        }

        return $items->filter(function ($specialty) use ($term, $selected) {
            return str_contains(mb_strtolower($specialty->name), $term)
                || str_contains(mb_strtolower((string) $specialty->code), $term)
                || (string) $specialty->id === $selected;
        })->values();
    }

    public function render(AdminScopeService $scope)
    {
        $admin = Auth::guard('admin')->user();
        $practiceQuery = Practice::query()->orderBy('legal_name');
        if ($admin) {
            $scope->scopePractices($practiceQuery, $admin);
        }

        return view('livewire.admin.provider.provider-create-page', [
            'users' => User::whereDoesntHave('providerDetails')->get(),
            'practices' => $practiceQuery->get(['id', 'legal_name', 'client_code']),
            'states' => UsStates::all(),
            'credentialTypes' => ProviderCredentialType::cases(),
            'specialtyOptions' => $this->filteredSpecialtyOptions(),
        ]);
    }
}
