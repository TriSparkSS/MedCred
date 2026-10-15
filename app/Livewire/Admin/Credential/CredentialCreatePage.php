<?php

namespace App\Livewire\Admin\Credential;

use App\Enums\EnrollmentLevel;
use App\Models\Admin;
use App\Models\CaseType;
use App\Models\DelayOwner;
use App\Models\DocumentType;
use App\Models\Location;
use App\Models\Payer;
use App\Models\Practice;
use App\Models\Priority;
use App\Models\ProviderDetails;
use App\Models\Status;
use App\Services\AdminScopeService;
use App\Services\CredentialingCaseService;
use App\Services\DocumentService;
use App\Services\TaskSyncService;
use App\Support\UsStates;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts::admin', ['title' => 'New Credentialing Application'])]
class CredentialCreatePage extends Component
{
    use WithFileUploads;

    public $formData = [];

    public $practiceLocations = [];

    public bool $confirmDuplicate = false;

    public string $duplicateWarning = '';

    public string $payerName = '';

    public array $additionalDocuments = [];

    public ?int $additionalDocumentTypeId = null;

    public bool $linkAdditionalDocsToPractice = true;

    public bool $linkAdditionalDocsToProvider = true;

    protected function rules(): array
    {
        return [
            'formData.provider_id' => 'required|exists:provider_details,id',
            'formData.practice_id' => 'required|exists:practices,id',
            'formData.location_id' => 'nullable|exists:locations,id',
            'formData.provider_practice_location_id' => 'nullable|exists:provider_practice_locations,id',
            'formData.payer_id' => 'nullable|required_without:payerName|exists:payers,id',
            'payerName' => 'nullable|required_without:formData.payer_id|string|max:255',
            'formData.case_type_id' => 'nullable|exists:case_types,id',
            'formData.enrollment_level' => ['required', Rule::enum(EnrollmentLevel::class)],
            'formData.status_id' => 'required|exists:statuses,id',
            'formData.delay_owner_id' => 'nullable|exists:delay_owners,id',
            'formData.priority_id' => 'nullable|exists:priorities,id',
            'formData.assigned_admin_id' => 'nullable|exists:admins,id',
            'formData.state' => ['nullable', 'string', 'size:2', Rule::in(UsStates::codes())],
            'formData.intake_date' => 'nullable|date',
            'formData.submission_date' => 'nullable|date',
            'formData.payer_follow_up_date' => 'nullable|date',
            'formData.expected_completion_date' => 'nullable|date',
            'formData.effective_date' => 'nullable|date',
            'formData.revalidation_due_date' => 'nullable|date',
            'formData.next_follow_up_date' => 'nullable|date',
            'formData.notes' => 'nullable|string|max:5000',
            'additionalDocuments.*' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
            'additionalDocumentTypeId' => 'nullable|exists:document_types,id',
        ];
    }

    public function mount(): void
    {
        $defaultStatus = Status::where('name', 'Not Started')->first()
            ?? Status::where('is_active', true)->orderBy('sort_order')->first();

        $this->formData = [
            'intake_date' => now()->toDateString(),
            'status_id' => $defaultStatus?->id,
            'delay_owner_id' => $defaultStatus?->delay_owner_id,
            'assigned_admin_id' => Auth::guard('admin')->id(),
            'enrollment_level' => EnrollmentLevel::Individual->value,
        ];

        if ($providerId = request()->query('provider')) {
            $this->formData['provider_id'] = $providerId;
            $this->applyProviderPractice($providerId);
        }

        if ($practiceId = request()->query('practice')) {
            $this->formData['practice_id'] = $practiceId;
            $this->formData['enrollment_level'] = EnrollmentLevel::Group->value;
            $this->updatedFormDataPracticeId($practiceId);
        }
    }

    public function updatedFormDataProviderId($value): void
    {
        $this->applyProviderPractice($value);
    }

    public function updatedFormDataPracticeId($value): void
    {
        $this->formData['location_id'] = null;
        $this->formData['provider_practice_location_id'] = null;
        $this->practiceLocations = $value
            ? Location::where('practice_id', $value)->orderByDesc('is_primary')->orderBy('name')->get()->toArray()
            : [];
    }

    public function updatedFormDataStatusId($value): void
    {
        if ($value) {
            $status = Status::find($value);
            if ($status?->delay_owner_id) {
                $this->formData['delay_owner_id'] = $status->delay_owner_id;
            }
        }
    }

    public function save(CredentialingCaseService $caseService, TaskSyncService $taskSync)
    {
        if (($this->formData['payer_id'] ?? '') === '') {
            $this->formData['payer_id'] = null;
        }

        if (empty($this->formData['state'])) {
            $derivedState = null;
            if (! empty($this->formData['location_id'])) {
                $derivedState = Location::find($this->formData['location_id'])?->state;
            }
            if (! $derivedState && ! empty($this->formData['practice_id'])) {
                $derivedState = Practice::with('primaryAddress')->find($this->formData['practice_id'])?->primaryAddress?->state;
            }
            if (! $derivedState && ! empty($this->formData['provider_id'])) {
                $derivedState = ProviderDetails::find($this->formData['provider_id'])?->state;
            }
            $normalizedState = UsStates::normalize($derivedState);
            if ($normalizedState && in_array($normalizedState, UsStates::codes(), true)) {
                $this->formData['state'] = $normalizedState;
            }
        }

        $this->resolvePayerFromName();
        $this->validate();

        $admin = Auth::guard('admin')->user();
        if ($admin && ! app(AdminScopeService::class)->canAccessPractice($admin, (int) $this->formData['practice_id'])) {
            abort(403, 'You do not have access to this practice.');
        }

        $adminId = $admin?->id;

        try {
            $case = $caseService->create($this->formData, $adminId, $this->confirmDuplicate);
        } catch (\InvalidArgumentException $e) {
            $this->duplicateWarning = $e->getMessage();
            $this->confirmDuplicate = false;
            flash()->warning($e->getMessage());

            return;
        }

        if ($case->notes) {
            $case->addActivity('note', $case->notes, $adminId);
        }

        if (! empty($this->additionalDocuments)) {
            $docService = app(DocumentService::class);
            $defaultTypeId = $this->additionalDocumentTypeId ?: DocumentType::where('name', 'Payer Correspondence')->value('id');
            foreach ($this->additionalDocuments as $file) {
                if (! $file) {
                    continue;
                }
                $docService->upload([
                    'title' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) ?: 'Application Document',
                    'document_type_id' => $defaultTypeId,
                    'credentialing_case_id' => $case->id,
                    'payer_id' => $case->payer_id,
                    'practice_id' => $this->linkAdditionalDocsToPractice ? $case->practice_id : null,
                    'provider_id' => $this->linkAdditionalDocsToProvider ? $case->provider_id : null,
                ], $file, $adminId);
            }
            $this->additionalDocuments = [];
        }

        $case->load('documentItems.documentType');
        foreach ($case->documentItems->where('is_required', true)->where('is_received', false) as $item) {
            $taskSync->ensureDocumentTask($case, $item, $adminId);
        }

        flash()->success('Credentialing application created: '.$case->case_number);

        return redirect()->route('admin.applications');
    }

    protected function applyProviderPractice(mixed $providerId): void
    {
        if (! $providerId) {
            return;
        }

        $practice = ProviderDetails::find($providerId)?->primaryPractice();

        $isGroup = ($this->formData['enrollment_level'] ?? '') === EnrollmentLevel::Group->value;
        if ($isGroup && ! empty($this->formData['practice_id'])) {
            $this->updatedFormDataPracticeId($this->formData['practice_id']);

            return;
        }

        if ($practice) {
            $this->formData['practice_id'] = $practice->id;
            $this->updatedFormDataPracticeId($practice->id);
        }
    }

    protected function resolvePayerFromName(): void
    {
        $name = trim($this->payerName);
        if ($name === '') {
            return;
        }

        $payer = Payer::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();

        if (! $payer) {
            $payer = Payer::create(['name' => $name, 'is_active' => true]);
        }

        $this->formData['payer_id'] = $payer->id;
    }

    public function saveAnyway(CredentialingCaseService $caseService, TaskSyncService $taskSync)
    {
        $this->confirmDuplicate = true;
        $this->save($caseService, $taskSync);
    }

    public function render()
    {
        $admin = Auth::guard('admin')->user();
        $scope = app(AdminScopeService::class);
        $providerQuery = ProviderDetails::with('user');
        $practiceQuery = Practice::orderBy('legal_name');
        if ($admin) {
            $scope->scopeProviders($providerQuery, $admin);
            $scope->scopePractices($practiceQuery, $admin);
        }

        $practices = $practiceQuery->get(['id', 'legal_name']);
        if (! empty($this->formData['provider_id'])) {
            $linked = ProviderDetails::find($this->formData['provider_id'])
                ?->practices()
                ->orderBy('legal_name')
                ->get(['practices.id', 'legal_name']);
            if ($linked?->isNotEmpty()) {
                $practices = $linked;
            }
        }

        return view('livewire.admin.credential.credential-create-page', [
            'providers' => $providerQuery->get(),
            'practices' => $practices,
            'payers' => Payer::where('is_active', true)->orderBy('name')->get(),
            'caseTypes' => CaseType::where('is_active', true)->orderBy('name')->get(),
            'statuses' => Status::where('is_active', true)->orderBy('sort_order')->get(),
            'priorities' => Priority::where('is_active', true)->orderBy('sort_order')->get(),
            'delayOwners' => DelayOwner::where('is_active', true)->orderBy('name')->get(),
            'admins' => Admin::assignable()->get(['id', 'name', 'username']),
            'documentTypes' => DocumentType::where('is_active', true)->orderBy('name')->get(),
            'duplicateWarning' => $this->duplicateWarning,
        ]);
    }
}
