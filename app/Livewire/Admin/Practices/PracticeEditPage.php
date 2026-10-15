<?php

namespace App\Livewire\Admin\Practices;

use App\Livewire\Admin\Practices\Concerns\ManagesPracticeForm;
use App\Models\Practice;
use App\Models\User;
use App\Services\AdminScopeService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts::admin', ['title' => 'Edit Practice'])]
class PracticeEditPage extends Component
{
    use ManagesPracticeForm, WithFileUploads;

    #[Locked]
    public $practiceId;

    #[Locked]
    public $addressIds = [];

    public $formData = [];

    public $addressData = [];

    public $alternativeAddressData = [];

    public $mailingAddressData = [];

    public $billingAddressData = [];

    public $document;

    #[Locked]
    public $existingDocumentPath;

    #[Locked]
    public $existingDocumentName;

    public $userData = [
        'password' => '',
    ];

    public function mount($practice): void
    {
        $practiceModel = Practice::with('addresses')->findOrFail($practice);
        $admin = Auth::guard('admin')->user();
        if ($admin && ! app(AdminScopeService::class)->canAccessPractice($admin, (int) $practiceModel->id)) {
            abort(403, 'You do not have access to this practice.');
        }
        $this->practiceId = $practiceModel->id;

        $addresses = $practiceModel->addresses->keyBy('type');

        $this->addressIds = [
            'primary' => $addresses->get('primary')?->id,
            'alternative' => $addresses->get('alternative')?->id,
            'mailing' => $addresses->get('mailing')?->id,
            'billing' => $addresses->get('billing')?->id,
        ];

        $this->formData = [
            'legal_name' => $practiceModel->legal_name,
            'client_code' => $practiceModel->client_code,
            'dba_name' => $practiceModel->dba_name,
            'ein_tin' => $practiceModel->ein_tin,
            'group_npi' => $practiceModel->group_npi,
            'taxonomy_code' => $practiceModel->taxonomy_code,
            'phone' => $practiceModel->phone,
            'fax' => $practiceModel->fax,
            'email' => $practiceModel->email,
            'website' => $practiceModel->website,
            'status' => $practiceModel->status?->value ?? $practiceModel->status,
            'license_number' => $practiceModel->license_number,
            'license_name' => $practiceModel->license_name,
            'license_effective_date' => $practiceModel->license_effective_date?->format('Y-m-d'),
            'license_expiry_date' => $practiceModel->license_expiry_date?->format('Y-m-d'),
            'bank_name' => $practiceModel->bank_name,
            'bank_account' => $practiceModel->bank_account,
            'bank_routing_number' => $practiceModel->bank_routing_number,
            'bank_address' => $practiceModel->bank_address,
            'bank_phone' => $practiceModel->bank_phone,
        ];

        $this->existingDocumentPath = $practiceModel->document_path;
        $this->existingDocumentName = $practiceModel->document_original_name;

        $this->addressData = $this->mapAddressFromModel($addresses->get('primary'));
        $this->alternativeAddressData = $this->mapAddressFromModel($addresses->get('alternative'));
        $this->mailingAddressData = $this->mapAddressFromModel($addresses->get('mailing'));
        $this->billingAddressData = $this->mapAddressFromModel($addresses->get('billing'));
    }

    protected function rules(): array
    {
        $practice = Practice::findOrFail($this->practiceId);

        return array_merge(
            $this->basePracticeRules(),
            [
                'formData.client_code' => Practice::clientCodeValidationRule().'|unique:practices,client_code,'.$this->practiceId,
                'formData.ein_tin' => 'nullable|string|max:50|unique:practices,ein_tin,'.$this->practiceId,
                'formData.group_npi' => 'nullable|string|max:50|unique:practices,group_npi,'.$this->practiceId,
                'formData.email' => 'required|email|max:255|unique:practices,email,'.$this->practiceId.'|unique:users,email,'.$practice->user_id,
                'userData.password' => 'nullable|string|min:6',
            ],
            $this->addressRules('addressData', true),
            $this->addressRules('alternativeAddressData'),
            $this->addressRules('mailingAddressData'),
            $this->addressRules('billingAddressData'),
        );
    }

    public function save()
    {
        $admin = Auth::guard('admin')->user();
        abort_unless($admin?->can('admin.practices.manage'), 403);
        abort_unless(app(AdminScopeService::class)->canAccessPractice($admin, (int) $this->practiceId), 403);

        $this->validate();

        $practice = Practice::findOrFail($this->practiceId);
        $canManagePortalCredentials = $admin->can('admin.portal-credentials.manage');

        DB::transaction(function () use ($practice, $canManagePortalCredentials) {
            $documentData = $this->storePracticeDocument(
                $this->document,
                $practice->document_path,
                $practice->document_original_name,
            );

            $practice->update([
                ...$this->formData,
                ...$documentData,
            ]);

            $user = User::findOrFail($practice->user_id);
            $userPayload = [
                'name' => $this->formData['legal_name'],
                'email' => $this->formData['email'],
                'phone' => $this->formData['phone'] ?? null,
            ];

            if ($canManagePortalCredentials && filled($this->userData['password'])) {
                $userPayload['password'] = $this->userData['password'];
            }

            $user->update($userPayload);
            $this->userData['password'] = '';

            $this->syncPracticeAddress($practice, 'primary', $this->addressData, $this->addressIds['primary']);
            $this->syncPracticeAddress($practice, 'alternative', $this->alternativeAddressData, $this->addressIds['alternative']);
            $this->syncPracticeAddress($practice, 'mailing', $this->mailingAddressData, $this->addressIds['mailing']);
            $this->syncPracticeAddress($practice, 'billing', $this->billingAddressData, $this->addressIds['billing']);
            $this->syncPracticeLicenseDocument($practice->fresh(), $this->document);
        });

        flash()->success('Practice updated successfully!');

        return redirect()->route('admin.practices');
    }

    public function render()
    {
        return view('livewire.admin.practices.practice-edit-page');
    }
}
