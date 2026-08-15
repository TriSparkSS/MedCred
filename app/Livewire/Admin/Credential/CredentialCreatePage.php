<?php

namespace App\Livewire\Admin\Credential;

use App\Models\Admin;
use App\Models\CaseType;
use App\Models\DelayOwner;
use App\Models\Location;
use App\Models\Payer;
use App\Models\Practice;
use App\Models\Priority;
use App\Models\ProviderDetails;
use App\Models\Status;
use App\Services\CredentialingCaseService;
use App\Services\TaskSyncService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::admin', ['title' => 'New Credentialing Application'])]
class CredentialCreatePage extends Component
{
    public $formData = [];

    public $practiceLocations = [];

    public bool $confirmDuplicate = false;

    public string $duplicateWarning = '';

    protected function rules(): array
    {
        return [
            'formData.provider_id' => 'required|exists:provider_details,id',
            'formData.practice_id' => 'required|exists:practices,id',
            'formData.location_id' => 'nullable|exists:locations,id',
            'formData.provider_practice_location_id' => 'nullable|exists:provider_practice_locations,id',
            'formData.payer_id' => 'required|exists:payers,id',
            'formData.case_type_id' => 'nullable|exists:case_types,id',
            'formData.status_id' => 'required|exists:statuses,id',
            'formData.delay_owner_id' => 'nullable|exists:delay_owners,id',
            'formData.priority_id' => 'nullable|exists:priorities,id',
            'formData.assigned_admin_id' => 'nullable|exists:admins,id',
            'formData.state' => 'nullable|string|max:50',
            'formData.intake_date' => 'nullable|date',
            'formData.submission_date' => 'nullable|date',
            'formData.payer_follow_up_date' => 'nullable|date',
            'formData.expected_completion_date' => 'nullable|date',
            'formData.effective_date' => 'nullable|date',
            'formData.revalidation_due_date' => 'nullable|date',
            'formData.next_follow_up_date' => 'nullable|date',
            'formData.notes' => 'nullable|string|max:5000',
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
        ];

        if ($providerId = request()->query('provider')) {
            $this->formData['provider_id'] = $providerId;
        }
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
        $this->validate();

        $admin = Auth::guard('admin')->user();
        if ($admin && ! app(\App\Services\AdminScopeService::class)->canAccessPractice($admin, (int) $this->formData['practice_id'])) {
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

        $case->load('documentItems.documentType');
        foreach ($case->documentItems->where('is_required', true)->where('is_received', false) as $item) {
            $taskSync->ensureDocumentTask($case, $item, $adminId);
        }

        flash()->success('Credentialing application created: ' . $case->case_number);

        return redirect()->route('admin.credentials');
    }

    public function saveAnyway(CredentialingCaseService $caseService, TaskSyncService $taskSync)
    {
        $this->confirmDuplicate = true;
        $this->save($caseService, $taskSync);
    }

    public function render()
    {
        $admin = Auth::guard('admin')->user();
        $scope = app(\App\Services\AdminScopeService::class);
        $providerQuery = ProviderDetails::with('user');
        $practiceQuery = Practice::orderBy('legal_name');
        if ($admin) {
            $scope->scopeProviders($providerQuery, $admin);
            $scope->scopePractices($practiceQuery, $admin);
        }

        return view('livewire.admin.credential.credential-create-page', [
            'providers' => $providerQuery->get(),
            'practices' => $practiceQuery->get(['id', 'legal_name']),
            'payers' => Payer::where('is_active', true)->orderBy('name')->get(),
            'caseTypes' => CaseType::where('is_active', true)->orderBy('name')->get(),
            'statuses' => Status::where('is_active', true)->orderBy('sort_order')->get(),
            'priorities' => Priority::where('is_active', true)->orderBy('sort_order')->get(),
            'delayOwners' => DelayOwner::where('is_active', true)->orderBy('name')->get(),
            'admins' => Admin::assignable()->get(['id', 'name', 'username']),
            'duplicateWarning' => $this->duplicateWarning,
        ]);
    }
}
