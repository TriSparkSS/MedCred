<?php

namespace App\Livewire\Admin\Provider;

use App\Enums\ProviderCredentialType;
use App\Enums\ProviderStatus;
use App\Livewire\Admin\Provider\Concerns\ManagesProviderCredentialRows;
use App\Livewire\Admin\Provider\Concerns\SelectsPracticeAddress;
use App\Livewire\Concerns\LooksUpUsZip;
use App\Models\DocumentType;
use App\Models\Location;
use App\Models\Practice;
use App\Models\ProviderDetails;
use App\Models\Specialty;
use App\Models\User;
use App\Services\AdminScopeService;
use App\Services\DocumentService;
use App\Services\ProviderLocationService;
use App\Support\UsStates;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts::admin', ['title' => 'Add Provider'])]
class ProviderCreatePage extends Component
{
    use LooksUpUsZip, ManagesProviderCredentialRows, WithFileUploads;
    use SelectsPracticeAddress {
        updatedPracticeId as protected selectPracticeAddress;
    }

    public $formData = [];

    public $malpracticeDocument = null;

    /**
     * Service locations to link on save. Each row:
     * source (existing|new), location_key, name, address1, address2, city, state, zip_code, role, is_primary.
     */
    public array $serviceLocations = [];

    public bool $showLocationModal = false;

    public array $locationForm = [];

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
            'formData.dob' => 'nullable|date',
            'formData.ssn' => 'nullable|string|max:20',
            'formData.specialty_id' => 'nullable|exists:specialties,id',
            'formData.degree' => 'nullable|string|max:100',
            'formData.provider_type' => 'nullable|string|max:100',
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
            'malpracticeDocument' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
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
            'formData.home_address' => 'nullable|string|max:500',
            'formData.home_city' => 'nullable|string|max:100',
            'formData.home_state' => 'nullable|string|max:50',
            'formData.home_zip' => 'nullable|string|max:20',
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
        $this->resetLocationForm();
    }

    public function updatedPracticeId($value): void
    {
        $this->selectPracticeAddress($value);

        // Service locations belong to the selected practice, so a new practice starts fresh.
        $this->serviceLocations = [];
        $this->showLocationModal = false;
    }

    public function openLocationModal(): void
    {
        if (! $this->practiceId) {
            $this->addError('practiceId', 'Select a practice before adding service locations.');

            return;
        }

        $this->assertPracticeInScope((int) $this->practiceId);
        $this->resetLocationForm();
        $this->resetValidation();
        $this->showLocationModal = true;
    }

    public function closeLocationModal(): void
    {
        $this->showLocationModal = false;
        $this->resetLocationForm();
        $this->resetValidation();
    }

    public function addServiceLocation(): void
    {
        abort_unless($this->practiceId, 422);
        $this->assertPracticeInScope((int) $this->practiceId);

        $mode = ($this->locationForm['mode'] ?? 'existing') === 'new' ? 'new' : 'existing';
        $available = array_keys($this->availableLocationOptions());

        if ($mode === 'existing') {
            $this->validate([
                'locationForm.location_key' => ['required', Rule::in($available)],
                'locationForm.role' => 'nullable|string|max:100',
            ], [
                'locationForm.location_key.required' => 'Select a location.',
                'locationForm.location_key.in' => 'Select a location that has not been added yet.',
            ]);

            $option = $this->addressOptions[$this->locationForm['location_key']];
            $row = [
                'source' => 'existing',
                'location_key' => (string) $this->locationForm['location_key'],
                'name' => (string) ($option['name'] ?? Str::before($option['label'], ' — ')),
                'address1' => (string) $option['address'],
                'address2' => '',
                'city' => (string) $option['city'],
                'state' => (string) $option['state'],
                'zip_code' => (string) $option['zip'],
            ];
        } else {
            $this->validate([
                'locationForm.name' => 'required|string|max:255',
                'locationForm.address1' => 'required|string|max:255',
                'locationForm.address2' => 'nullable|string|max:255',
                'locationForm.city' => 'required|string|max:100',
                'locationForm.state' => ['required', 'string', 'size:2', Rule::in(UsStates::codes())],
                'locationForm.zip_code' => 'required|string|max:20',
                'locationForm.role' => 'nullable|string|max:100',
            ], [], [
                'locationForm.name' => 'location name',
                'locationForm.address1' => 'address',
                'locationForm.city' => 'city',
                'locationForm.state' => 'state',
                'locationForm.zip_code' => 'zip code',
            ]);

            $row = [
                'source' => 'new',
                'location_key' => '',
                'name' => trim((string) $this->locationForm['name']),
                'address1' => trim((string) $this->locationForm['address1']),
                'address2' => trim((string) ($this->locationForm['address2'] ?? '')),
                'city' => trim((string) $this->locationForm['city']),
                'state' => strtoupper((string) $this->locationForm['state']),
                'zip_code' => trim((string) $this->locationForm['zip_code']),
            ];
        }

        $row['role'] = trim((string) ($this->locationForm['role'] ?? ''));
        $row['is_primary'] = $this->serviceLocations === [] || (bool) ($this->locationForm['is_primary'] ?? false);

        if ($row['is_primary']) {
            foreach ($this->serviceLocations as $i => $existing) {
                $this->serviceLocations[$i]['is_primary'] = false;
            }
        }

        $this->serviceLocations[] = $row;

        // The provider's own address is required; fill it from the first location when still blank.
        if (blank($this->formData['address'] ?? null)) {
            $this->formData['address'] = trim($row['address1'].($row['address2'] !== '' ? ', '.$row['address2'] : ''));
            $this->formData['city'] = $row['city'];
            $this->formData['state'] = $row['state'];
            $this->formData['zip'] = $row['zip_code'];
        }

        $this->closeLocationModal();
    }

    public function removeServiceLocation(int $index): void
    {
        if (! isset($this->serviceLocations[$index])) {
            return;
        }

        $wasPrimary = (bool) $this->serviceLocations[$index]['is_primary'];
        unset($this->serviceLocations[$index]);
        $this->serviceLocations = array_values($this->serviceLocations);

        if ($wasPrimary && $this->serviceLocations !== []) {
            $this->serviceLocations[0]['is_primary'] = true;
        }
    }

    public function setPrimaryServiceLocation(int $index): void
    {
        if (! isset($this->serviceLocations[$index])) {
            return;
        }

        foreach ($this->serviceLocations as $i => $row) {
            $this->serviceLocations[$i]['is_primary'] = $i === $index;
        }
    }

    /**
     * Practice addresses / facilities not yet added as a service location.
     */
    protected function availableLocationOptions(): array
    {
        $taken = collect($this->serviceLocations)
            ->where('source', 'existing')
            ->pluck('location_key')
            ->all();

        return array_diff_key($this->addressOptions, array_flip($taken));
    }

    protected function resetLocationForm(): void
    {
        $this->locationForm = [
            'mode' => 'existing',
            'location_key' => '',
            'name' => '',
            'address1' => '',
            'address2' => '',
            'city' => '',
            'state' => '',
            'zip_code' => '',
            'role' => '',
            'is_primary' => $this->serviceLocations === [],
        ];
    }

    protected function persistServiceLocations(ProviderDetails $provider): void
    {
        $practiceId = (int) $this->practiceId;
        $service = app(ProviderLocationService::class);

        foreach ($this->serviceLocations as $row) {
            if ($row['source'] === 'new') {
                $location = Location::create([
                    'practice_id' => $practiceId,
                    'name' => $row['name'],
                    'address1' => $row['address1'],
                    'address2' => $row['address2'] ?: null,
                    'city' => $row['city'],
                    'state' => $row['state'],
                    'zip_code' => $row['zip_code'],
                    'country' => 'United States',
                    'status' => 'active',
                    'is_primary' => false,
                ]);
                $locationKey = (string) $location->id;
            } else {
                // resolveLocation() accepts "address:ID" or a bare location id.
                $locationKey = str_starts_with($row['location_key'], 'location:')
                    ? substr($row['location_key'], strlen('location:'))
                    : $row['location_key'];
            }

            $service->saveLink($provider, [
                'practice_id' => $practiceId,
                'location_id' => $locationKey,
                'role' => $row['role'] ?: null,
                'is_primary' => (bool) $row['is_primary'],
            ]);
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
        $this->assertPracticeInScope((int) $this->practiceId);

        $password = $canManagePortalCredentials && filled($this->userData['password'])
            ? $this->userData['password']
            : Str::password(16);

        // One transaction so a failure (e.g. a duplicate location link) never leaves a half-created provider.
        $provider = DB::transaction(function () use ($password) {
            $user = User::create([
                'name' => $this->userData['name'],
                'email' => $this->userData['email'],
                'phone' => $this->userData['phone'] ?? null,
                'password' => $password,
            ]);

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

            $this->persistServiceLocations($provider);
            $this->persistCredentialRows($provider, false);

            if ($this->malpracticeDocument) {
                $malpracticeType = DocumentType::firstOrCreate(
                    ['name' => 'Malpractice Insurance'],
                    ['is_active' => true, 'is_state_specific' => false]
                );
                $carrier = trim((string) ($this->formData['malpractice_carrier'] ?? ''));
                app(DocumentService::class)->upload([
                    'title' => 'Malpractice Insurance'.($carrier !== '' ? ' - '.$carrier : ''),
                    'document_number' => $this->formData['malpractice_policy_number'] ?? null,
                    'effective_date' => ! empty($this->formData['malpractice_effective_date']) ? $this->formData['malpractice_effective_date'] : null,
                    'expiry_date' => ! empty($this->formData['malpractice_expiry']) ? $this->formData['malpractice_expiry'] : null,
                    'provider_id' => $provider->id,
                    'practice_id' => $this->practiceId ? (int) $this->practiceId : null,
                    'document_type_id' => $malpracticeType->id,
                ], $this->malpracticeDocument, Auth::guard('admin')->id());
                $this->malpracticeDocument = null;
            }

            return $provider;
        });

        flash()->success('Provider and user created successfully!');

        return redirect()->route('admin.providers.show', $provider->id);
    }

    private function normalizeMalpracticeFields(array $data): array
    {
        foreach (['dob', 'malpractice_coverage_each_occurrence', 'malpractice_coverage_aggregate', 'malpractice_effective_date'] as $field) {
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
            'availableLocationOptions' => $this->availableLocationOptions(),
            'latestMalpracticeDoc' => null,
        ]);
    }
}
