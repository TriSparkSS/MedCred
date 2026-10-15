<?php

namespace App\Livewire\Admin\Credential;

use App\Enums\EnrollmentLevel;
use App\Models\Admin;
use App\Models\CaseType;
use App\Models\CredentialingCase;
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
use App\Support\UsStates;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts::admin', ['title' => 'Edit Credentialing Application'])]
class CredentialEditPage extends Component
{
    use WithFileUploads;

    #[Locked]
    public int $caseId;

    public array $formData = [];

    public array $practiceLocations = [];

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

    public function mount($case): void
    {
        $admin = Auth::guard('admin')->user();
        abort_unless($admin?->can('admin.credentials.edit'), 403);

        $caseModel = $case instanceof CredentialingCase
            ? $case
            : CredentialingCase::findOrFail($case);

        abort_unless(app(AdminScopeService::class)->canAccessCase($admin, $caseModel), 403);

        $this->caseId = $caseModel->id;
        $this->practiceLocations = $caseModel->practice_id
            ? Location::where('practice_id', $caseModel->practice_id)->orderByDesc('is_primary')->orderBy('name')->get()->toArray()
            : [];

        $this->formData = [
            'provider_id' => $caseModel->provider_id,
            'practice_id' => $caseModel->practice_id,
            'location_id' => $caseModel->location_id,
            'provider_practice_location_id' => $caseModel->provider_practice_location_id,
            'payer_id' => $caseModel->payer_id,
            'case_type_id' => $caseModel->case_type_id,
            'enrollment_level' => $caseModel->enrollment_level instanceof EnrollmentLevel
                ? $caseModel->enrollment_level->value
                : ($caseModel->enrollment_level ?: EnrollmentLevel::Individual->value),
            'status_id' => $caseModel->status_id,
            'delay_owner_id' => $caseModel->delay_owner_id,
            'priority_id' => $caseModel->priority_id,
            'assigned_admin_id' => $caseModel->assigned_admin_id,
            'intake_date' => $caseModel->intake_date?->format('Y-m-d'),
            'submission_date' => $caseModel->submission_date?->format('Y-m-d'),
            'payer_follow_up_date' => $caseModel->payer_follow_up_date?->format('Y-m-d'),
            'expected_completion_date' => $caseModel->expected_completion_date?->format('Y-m-d'),
            'effective_date' => $caseModel->effective_date?->format('Y-m-d'),
            'revalidation_due_date' => $caseModel->revalidation_due_date?->format('Y-m-d'),
            'next_follow_up_date' => $caseModel->next_follow_up_date?->format('Y-m-d'),
            'notes' => $caseModel->notes ?? '',
        ];
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

    public function save(CredentialingCaseService $caseService)
    {
        $admin = Auth::guard('admin')->user();
        abort_unless($admin?->can('admin.credentials.edit'), 403);

        $case = CredentialingCase::findOrFail($this->caseId);
        $scope = app(AdminScopeService::class);
        abort_unless($scope->canAccessCase($admin, $case), 403);

        if (($this->formData['payer_id'] ?? '') === '') {
            $this->formData['payer_id'] = null;
        }

        $this->resolvePayerFromName();

        foreach ([
            'location_id', 'provider_practice_location_id', 'case_type_id',
            'delay_owner_id', 'priority_id', 'assigned_admin_id',
            'intake_date', 'submission_date', 'payer_follow_up_date',
            'expected_completion_date', 'effective_date', 'revalidation_due_date', 'next_follow_up_date',
        ] as $nullableKey) {
            if (($this->formData[$nullableKey] ?? '') === '') {
                $this->formData[$nullableKey] = null;
            }
        }

        $this->validate();
        abort_unless($scope->canAccessPractice($admin, (int) $this->formData['practice_id']), 403);

        $adminId = $admin->id;
        $newStatusId = (int) $this->formData['status_id'];
        if ($newStatusId !== (int) $case->status_id) {
            $case = $caseService->changeStatus($case, $newStatusId, $adminId, 'Status updated via Edit Application');
        }

        $derivedState = $case->state;
        if (! empty($this->formData['location_id'])) {
            $derivedState = Location::find($this->formData['location_id'])?->state ?: $derivedState;
        } elseif (! $derivedState && ! empty($this->formData['practice_id'])) {
            $derivedState = Practice::with('primaryAddress')->find($this->formData['practice_id'])?->primaryAddress?->state;
        }
        $normalizedState = UsStates::normalize($derivedState);

        $updatePayload = $this->formData;
        unset($updatePayload['status_id']);
        if ($normalizedState && in_array($normalizedState, UsStates::codes(), true)) {
            $updatePayload['state'] = $normalizedState;
        }

        $case->update([
            ...$updatePayload,
            'last_action_at' => now(),
        ]);

        if ($case->wasChanged()) {
            $case->addActivity('system', 'Application details updated', $adminId);
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

        flash()->success('Application updated successfully.');

        return redirect()->route('admin.credentials.show', $case->id);
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

        $case = CredentialingCase::findOrFail($this->caseId);

        return view('livewire.admin.credential.credential-edit-page', [
            'case' => $case,
            'providers' => $providerQuery->get(),
            'practices' => $practiceQuery->get(['id', 'legal_name']),
            'payers' => Payer::where('is_active', true)->orderBy('name')->get(),
            'caseTypes' => CaseType::where('is_active', true)->orderBy('name')->get(),
            'statuses' => Status::where('is_active', true)->orderBy('sort_order')->get(),
            'priorities' => Priority::where('is_active', true)->orderBy('sort_order')->get(),
            'delayOwners' => DelayOwner::where('is_active', true)->orderBy('name')->get(),
            'admins' => Admin::assignable()->get(['id', 'name', 'username']),
            'documentTypes' => DocumentType::where('is_active', true)->orderBy('name')->get(),
        ]);
    }
}
