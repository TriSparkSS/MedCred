<?php

namespace App\Livewire\Admin\Practices;

use App\Livewire\Admin\Practices\Concerns\ManagesPracticeForm;
use App\Models\Practice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts::admin', ['title' => 'Edit Practice'])]
class PracticeEditPage extends Component
{
    use ManagesPracticeForm, WithFileUploads;

    public $practiceId;

    public $addressIds = [];

    public $formData = [];

    public $addressData = [];

    public $alternativeAddressData = [];

    public $mailingAddressData = [];

    public $billingAddressData = [];

    public $document;

    public $existingDocumentPath;

    public $existingDocumentName;

    public function mount($practice): void
    {
        $practiceModel = Practice::with('addresses')->findOrFail($practice);
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
                'formData.ein_tin' => 'nullable|string|max:50|unique:practices,ein_tin,' . $this->practiceId,
                'formData.group_npi' => 'nullable|string|max:50|unique:practices,group_npi,' . $this->practiceId,
                'formData.email' => 'required|email|max:255|unique:practices,email,' . $this->practiceId . '|unique:users,email,' . $practice->user_id,
            ],
            $this->addressRules('addressData', true),
            $this->addressRules('alternativeAddressData'),
            $this->addressRules('mailingAddressData'),
            $this->addressRules('billingAddressData'),
        );
    }

    public function save()
    {
        $this->validate();

        $practice = Practice::findOrFail($this->practiceId);

        DB::transaction(function () use ($practice) {
            $documentData = $this->storePracticeDocument(
                $this->document,
                $this->existingDocumentPath,
                $this->existingDocumentName,
            );

            $practice->update([
                ...$this->formData,
                ...$documentData,
            ]);

            $user = User::findOrFail($practice->user_id);
            $user->update([
                'name' => $this->formData['legal_name'],
                'email' => $this->formData['email'],
                'phone' => $this->formData['phone'] ?? null,
            ]);

            $this->syncPracticeAddress($practice, 'primary', $this->addressData, $this->addressIds['primary']);
            $this->syncPracticeAddress($practice, 'alternative', $this->alternativeAddressData, $this->addressIds['alternative']);
            $this->syncPracticeAddress($practice, 'mailing', $this->mailingAddressData, $this->addressIds['mailing']);
            $this->syncPracticeAddress($practice, 'billing', $this->billingAddressData, $this->addressIds['billing']);
        });

        flash()->success('Practice updated successfully!');

        return redirect()->route('admin.practices');
    }

    public function render()
    {
        return view('livewire.admin.practices.practice-edit-page');
    }
}
