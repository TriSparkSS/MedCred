<?php

namespace App\Livewire\Admin\Practices;

use App\Models\Practice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::admin', ['title' => 'Add Practice'])]
class PracticeCreatePage extends Component
{
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

    protected $rules = [
        'formData.legal_name' => 'required|string|max:255',
        'formData.dba_name' => 'nullable|string|max:255',
        'formData.ein_tin' => 'nullable|string|max:50|unique:practices,ein_tin',
        'formData.group_npi' => 'nullable|string|max:50|unique:practices,group_npi',
        'formData.taxonomy_code' => 'nullable|string|max:50',
        'formData.phone' => 'nullable|string|max:30',
        'formData.fax' => 'nullable|string|max:30',
        'formData.email' => 'required|email|max:255|unique:practices,email|unique:users,email',
        'formData.website' => 'nullable|max:255',
        'formData.status' => 'required|in:pending,active,inactive',
        'userData.password' => 'required|string|min:6',
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

            $practice = Practice::create([
                ...$this->formData,
                'user_id' => $user->id,
            ]);

            $practice->addresses()->create($this->addressData);
        });

        flash()->success('Practice and user created successfully!');

        return redirect()->route('admin.practices');
    }

    public function render()
    {
        return view('livewire.admin.practices.practice-create-page');
    }
}
