<?php

namespace App\Livewire\Admin\Practices;

use App\Livewire\Admin\Practices\Concerns\ManagesPracticeForm;
use App\Models\Practice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts::admin', ['title' => 'Add Practice'])]
class PracticeCreatePage extends Component
{
    use ManagesPracticeForm, WithFileUploads;

    public $formData = [
        'status' => 'pending',
    ];

    public $userData = [
        'password' => '',
    ];

    public $addressData = [
        'country' => 'United States',
        'status' => 'active',
    ];

    public $alternativeAddressData = [];

    public $mailingAddressData = [];

    public $billingAddressData = [];

    public $document;

    public function mount(): void
    {
        $this->alternativeAddressData = $this->emptyAddressDefaults();
        $this->mailingAddressData = $this->emptyAddressDefaults();
        $this->billingAddressData = $this->emptyAddressDefaults();
    }

    protected function rules(): array
    {
        return array_merge(
            $this->basePracticeRules(),
            [
                'userData.password' => 'required|string|min:6',
                'formData.client_code' => 'required|string|size:3|regex:/^[A-Za-z]{3}$/|unique:practices,client_code',
                'formData.ein_tin' => 'nullable|string|max:50|unique:practices,ein_tin',
                'formData.group_npi' => 'nullable|string|max:50|unique:practices,group_npi',
                'formData.email' => 'required|email|max:255|unique:practices,email|unique:users,email',
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

        DB::transaction(function () {
            $user = User::create([
                'name' => $this->formData['legal_name'],
                'email' => $this->formData['email'],
                'phone' => $this->formData['phone'] ?? null,
                'password' => $this->userData['password'],
            ]);

            if (method_exists($user, 'assignRole')) {
                $user->assignRole('practice');
            }

            $documentData = $this->storePracticeDocument($this->document);

            $practice = Practice::create([
                ...$this->formData,
                ...$documentData,
                'user_id' => $user->id,
            ]);

            $this->syncPracticeAddress($practice, 'primary', $this->addressData);
            $this->syncPracticeAddress($practice, 'alternative', $this->alternativeAddressData);
            $this->syncPracticeAddress($practice, 'mailing', $this->mailingAddressData);
            $this->syncPracticeAddress($practice, 'billing', $this->billingAddressData);
        });

        flash()->success('Practice and user created successfully!');

        return redirect()->route('admin.practices');
    }

    public function render()
    {
        return view('livewire.admin.practices.practice-create-page');
    }
}
