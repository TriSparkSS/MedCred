<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check();
    }

    public function rules(): array
    {
        return [
            'userData.name' => 'required|string|max:255',
            'userData.email' => 'required|email|unique:users,email',
            'userData.phone' => 'nullable|string|max:30',
            'userData.password' => 'required|string|min:6',
            'formData.npi' => 'required|string|max:50',
            'formData.specialty_id' => 'nullable|exists:specialties,id',
            'formData.status' => 'required|in:pending,approved,rejected',
        ];
    }
}
