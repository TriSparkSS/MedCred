<?php

namespace App\Livewire\Admin\Practices;

use App\Models\Practice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::admin', ['title' => 'Edit Practice'])]
class PracticeEditPage extends Component
{
    public $practiceId;
    public $addressId;
    public $formData = [];
    public $addressData = [];

    protected $rules = [
        'formData.legal_name' => 'required|string|max:255',
        'formData.dba_name' => 'nullable|string|max:255',
        'formData.ein_tin' => 'nullable|string|max:50',
        'formData.group_npi' => 'nullable|string|max:50',
        'formData.taxonomy_code' => 'nullable|string|max:50',
        'formData.phone' => 'nullable|string|max:30',
        'formData.fax' => 'nullable|string|max:30',
        'formData.email' => 'required|email|max:255',
        'formData.website' => 'nullable|url|max:255',
        'formData.status' => 'required|in:pending,active,inactive',
        'addressData.location_name' => 'nullable|string|max:255',
        'addressData.address1' => 'required|string|max:255',
        'addressData.address2' => 'nullable|string|max:255',
        'addressData.city' => 'required|string|max:100',
        'addressData.state' => 'required|string|max:100',
        'addressData.zip_code' => 'required|string|max:20',
        'addressData.country' => 'required|string|max:100',
        'addressData.phone' => 'nullable|string|max:30',
        'addressData.fax' => 'nullable|string|max:30',
        'addressData.status' => 'required|in:active,inactive',
    ];

    public function mount($practice)
    {
        $practiceModel = Practice::with('primaryAddress')->findOrFail($practice);
        $this->practiceId = $practiceModel->id;
        $this->addressId = $practiceModel->primaryAddress?->id;

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
        ];

        $this->addressData = [
            'location_name' => $practiceModel->primaryAddress->location_name ?? '',
            'address1' => $practiceModel->primaryAddress->address1 ?? '',
            'address2' => $practiceModel->primaryAddress->address2 ?? '',
            'city' => $practiceModel->primaryAddress->city ?? '',
            'state' => $practiceModel->primaryAddress->state ?? '',
            'zip_code' => $practiceModel->primaryAddress->zip_code ?? '',
            'country' => $practiceModel->primaryAddress->country ?? 'United States',
            'phone' => $practiceModel->primaryAddress->phone ?? '',
            'fax' => $practiceModel->primaryAddress->fax ?? '',
            'status' => $practiceModel->primaryAddress->status ?? 'active',
        ];
    }

    public function save()
    {
        $practice = Practice::findOrFail($this->practiceId);
        $this->rules['formData.ein_tin'] = 'nullable|string|max:50|unique:practices,ein_tin,' . $this->practiceId;
        $this->rules['formData.group_npi'] = 'nullable|string|max:50|unique:practices,group_npi,' . $this->practiceId;
        $this->rules['formData.email'] = 'required|email|max:255|unique:practices,email,' . $this->practiceId . '|unique:users,email,' . $practice->user_id;
        $this->validate();

        DB::transaction(function () use ($practice) {
            $practice->update($this->formData);

            $user = User::findOrFail($practice->user_id);
            $user->update([
                'name' => $this->formData['legal_name'],
                'email' => $this->formData['email'],
                'phone' => $this->formData['phone'] ?? null,
            ]);

            if ($this->addressId) {
                $practice->addresses()->whereKey($this->addressId)->update($this->addressData);
            } else {
                $practice->addresses()->create($this->addressData);
            }
        });

        flash()->success('Practice updated successfully!');

        return redirect()->route('admin.practices');
    }

    public function render()
    {
        return view('livewire.admin.practices.practice-edit-page');
    }
}
