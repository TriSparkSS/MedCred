<?php

namespace App\Livewire\Admin\Provider;

use App\Enums\ProviderCredentialType;
use App\Enums\ProviderStatus;
use App\Livewire\Admin\Provider\Concerns\ManagesProviderCredentialRows;
use App\Livewire\Admin\Provider\Concerns\SelectsPracticeAddress;
use App\Livewire\Concerns\LooksUpUsZip;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Practice;
use App\Models\ProviderDetails;
use App\Models\Specialty;
use App\Models\User;
use App\Services\AdminScopeService;
use App\Services\DocumentService;
use App\Support\UsStates;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts::admin', ['title' => 'Edit Provider'])]
class ProviderEditPage extends Component
{
    use LooksUpUsZip, ManagesProviderCredentialRows, SelectsPracticeAddress, WithFileUploads;

    #[Locked]
    public $providerId;

    public $formData = [];

    public $malpracticeDocument = null;

    public $userData = [];

    public $specialties = [];

    public string $specialtySearch = '';

    protected function rules(): array
    {
        $provider = $this->providerId ? ProviderDetails::find($this->providerId) : null;

        return [
            'userData.name' => 'required|string|max:255',
            'userData.email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($provider?->user_id),
            ],
            'userData.phone' => 'nullable|string|max:30',
            'userData.password' => 'nullable|string|min:6',
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
            'practiceId' => 'nullable|exists:practices,id',
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
            'credentialRows.*.credential_type' => 'nullable|string',
            'credentialRows.*.state' => 'nullable|string|max:2',
            'credentialRows.*.number' => 'nullable|string|max:50',
            'credentialRows.*.expiry_date' => 'nullable|date',
        ];
    }

    public function mount($provider)
    {
        $admin = Auth::guard('admin')->user();
        abort_unless($admin?->can('admin.providers.edit'), 403);

        $this->providerId = $provider;
        $this->specialties = Specialty::orderBy('name')->get();

        $providerModel = ProviderDetails::with(['user', 'practices'])->findOrFail($this->providerId);
        if (! app(AdminScopeService::class)->canAccessProvider($admin, $providerModel)) {
            abort(403, 'You do not have access to this provider.');
        }

        $this->formData = [
            'dob' => $providerModel->dob?->format('Y-m-d'),
            'ssn' => $providerModel->ssn,
            'specialty_id' => $providerModel->specialty_id,
            'degree' => $providerModel->degree,
            'provider_type' => $providerModel->provider_type,
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
            'home_address' => $providerModel->home_address,
            'home_city' => $providerModel->home_city,
            'home_state' => UsStates::normalize($providerModel->home_state) ?? $providerModel->home_state,
            'home_zip' => $providerModel->home_zip,
            'status' => $providerModel->status instanceof ProviderStatus
                ? $providerModel->status->value
                : $providerModel->status,
            'status_remarks' => $providerModel->status_remarks,
        ];

        $this->userData = [
            'name' => $providerModel->user->name ?? '',
            'email' => $providerModel->user->email ?? '',
            'phone' => $providerModel->user->phone ?? '',
            'password' => '',
        ];

        $linked = $providerModel->practices
            ->sortByDesc(fn ($practice) => (int) ($practice->pivot->primary_flag ?? 0))
            ->values();
        $this->practiceId = (string) ($linked->first()?->id ?? '');
        $this->loadAddressOptions($this->practiceId);
        $this->matchAddressKeyFromCurrentFields();
        $this->loadCredentialRows($providerModel);
    }

    public function save()
    {
        $admin = Auth::guard('admin')->user();
        abort_unless($admin?->can('admin.providers.edit'), 403);

        $provider = ProviderDetails::with('practices')->findOrFail($this->providerId);
        abort_unless(app(AdminScopeService::class)->canAccessProvider($admin, $provider), 403);

        $rules = $this->rules();
        $rules['formData.npi'] = 'required|string|max:50|unique:provider_details,npi,'.$this->providerId;
        $this->validate($rules);

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

        if ($this->practiceId) {
            $this->assertPracticeInScope((int) $this->practiceId);
            $provider->practices()->syncWithoutDetaching([
                (int) $this->practiceId => ['primary_flag' => true],
            ]);
        }

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

        try {
            $this->persistCredentialRows($provider->fresh(), true);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->getMessageBag());
            flash()->error('Provider saved, but a credential row could not be stored. Check license/DEA/CDS rows.');

            return;
        }

        flash()->success('Provider updated successfully!');

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

    public function render(AdminScopeService $scope)
    {
        $admin = Auth::guard('admin')->user();
        $practiceQuery = Practice::query()->orderBy('legal_name');
        if ($admin) {
            $scope->scopePractices($practiceQuery, $admin);
        }

        $latestMalpracticeDoc = Document::query()
            ->where('provider_id', $this->providerId)
            ->whereHas('documentType', fn ($q) => $q->where('name', 'Malpractice Insurance'))
            ->latest('id')
            ->first();

        return view('livewire.admin.provider.provider-edit-page', [
            'practices' => $practiceQuery->get(['id', 'legal_name', 'client_code']),
            'specialtyOptions' => $this->filteredSpecialtyOptions(),
            'states' => UsStates::all(),
            'credentialTypes' => ProviderCredentialType::cases(),
            'latestMalpracticeDoc' => $latestMalpracticeDoc,
        ]);
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
}
