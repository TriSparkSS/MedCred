<?php

namespace App\Livewire\Admin\Practices\Concerns;

use App\Models\Address;
use App\Models\Practice;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

trait ManagesPracticeForm
{
    protected function basePracticeRules(): array
    {
        return [
            'formData.legal_name' => 'required|string|max:255',
            'formData.client_code' => 'required|string|size:3|regex:/^[A-Za-z]{3}$/',
            'formData.dba_name' => 'nullable|string|max:255',
            'formData.ein_tin' => 'nullable|string|max:50',
            'formData.group_npi' => 'nullable|string|max:50',
            'formData.taxonomy_code' => 'nullable|string|max:50',
            'formData.phone' => 'nullable|string|max:30',
            'formData.fax' => 'nullable|string|max:30',
            'formData.email' => 'required|email|max:255',
            'formData.website' => 'nullable|max:255',
            'formData.status' => 'required|in:pending,active,inactive',
            'formData.license_number' => 'nullable|string|max:50',
            'formData.bank_name' => 'nullable|string|max:255',
            'formData.bank_account' => 'nullable|string|max:50',
            'formData.bank_routing_number' => 'nullable|string|max:20|regex:/^[0-9]+$/',
            'formData.bank_address' => 'nullable|string|max:500',
            'formData.bank_phone' => 'nullable|string|max:30',
            'document' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
        ];
    }

    protected function addressRules(string $prefix, bool $required = false): array
    {
        $requiredRule = $required ? 'required' : 'nullable';

        return [
            "{$prefix}.location_name" => 'nullable|string|max:255',
            "{$prefix}.address1" => "{$requiredRule}|string|max:255",
            "{$prefix}.address2" => 'nullable|string|max:255',
            "{$prefix}.city" => "{$requiredRule}|string|max:100",
            "{$prefix}.state" => "{$requiredRule}|string|max:100",
            "{$prefix}.zip_code" => "{$requiredRule}|string|max:20",
            "{$prefix}.county" => 'nullable|string|max:100',
            "{$prefix}.country" => "{$requiredRule}|string|max:100",
            "{$prefix}.phone" => 'nullable|string|max:30',
            "{$prefix}.fax" => 'nullable|string|max:30',
            "{$prefix}.status" => 'nullable|in:active,inactive',
        ];
    }

    protected function emptyAddressDefaults(): array
    {
        return [
            'location_name' => '',
            'address1' => '',
            'address2' => '',
            'city' => '',
            'state' => '',
            'zip_code' => '',
            'county' => '',
            'country' => 'United States',
            'phone' => '',
            'fax' => '',
            'status' => 'active',
        ];
    }

    protected function mapAddressFromModel(?Address $address): array
    {
        if (! $address) {
            return $this->emptyAddressDefaults();
        }

        return [
            'location_name' => $address->location_name ?? '',
            'address1' => $address->address1 ?? '',
            'address2' => $address->address2 ?? '',
            'city' => $address->city ?? '',
            'state' => $address->state ?? '',
            'zip_code' => $address->zip_code ?? '',
            'county' => $address->county ?? '',
            'country' => $address->country ?? 'United States',
            'phone' => $address->phone ?? '',
            'fax' => $address->fax ?? '',
            'status' => $address->status ?? 'active',
        ];
    }

    protected function syncPracticeAddress(Practice $practice, string $type, array $data, ?int $addressId = null): void
    {
        if (blank($data['address1'] ?? null)) {
            if ($addressId) {
                $practice->addresses()->whereKey($addressId)->delete();
            }

            return;
        }

        $payload = [
            'type' => $type,
            'location_name' => $data['location_name'] ?? null,
            'address1' => $data['address1'],
            'address2' => $data['address2'] ?? null,
            'city' => $data['city'],
            'state' => $data['state'],
            'zip_code' => $data['zip_code'],
            'county' => $data['county'] ?? null,
            'country' => $data['country'] ?? 'United States',
            'phone' => $data['phone'] ?? null,
            'fax' => $data['fax'] ?? null,
            'status' => $data['status'] ?? 'active',
        ];

        if ($addressId) {
            $practice->addresses()->whereKey($addressId)->update($payload);

            return;
        }

        $practice->addresses()->create($payload);
    }

    protected function storePracticeDocument(?UploadedFile $document, ?string $existingPath = null, ?string $existingName = null): array
    {
        if (! $document) {
            return [
                'document_path' => $existingPath,
                'document_original_name' => $existingName,
            ];
        }

        if ($existingPath) {
            Storage::disk('public')->delete($existingPath);
        }

        return [
            'document_path' => $document->store('practice-documents', 'public'),
            'document_original_name' => $document->getClientOriginalName(),
        ];
    }
}
