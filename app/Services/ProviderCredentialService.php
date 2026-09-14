<?php

namespace App\Services;

use App\Enums\ProviderCredentialStatus;
use App\Enums\ProviderCredentialType;
use App\Models\Document;
use App\Models\ProviderCredential;
use App\Models\ProviderDetails;
use App\Support\UsStates;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProviderCredentialService
{
    public function save(ProviderDetails $provider, array $data, ?int $credentialId = null): ProviderCredential
    {
        $type = $data['credential_type'] instanceof ProviderCredentialType
            ? $data['credential_type']
            : ProviderCredentialType::from((string) $data['credential_type']);

        $state = UsStates::normalize($data['state'] ?? null);
        if (! $state) {
            throw ValidationException::withMessages(['formData.state' => 'A valid US state is required.']);
        }

        $duplicate = ProviderCredential::query()
            ->where('provider_id', $provider->id)
            ->where('credential_type', $type->value)
            ->where('state', $state)
            ->when($credentialId, fn ($q) => $q->whereKeyNot($credentialId))
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'formData.state' => 'This provider already has a '.$type->label().' for '.$state.'.',
            ]);
        }

        $isPrimary = (bool) ($data['is_primary'] ?? false);
        if ($type !== ProviderCredentialType::License) {
            $isPrimary = false;
        }

        return DB::transaction(function () use ($provider, $data, $credentialId, $type, $state, $isPrimary) {
            if ($isPrimary) {
                ProviderCredential::query()
                    ->where('provider_id', $provider->id)
                    ->where('credential_type', ProviderCredentialType::License->value)
                    ->when($credentialId, fn ($q) => $q->whereKeyNot($credentialId))
                    ->update(['is_primary' => false]);
            }

            $payload = [
                'provider_id' => $provider->id,
                'credential_type' => $type->value,
                'state' => $state,
                'number' => trim((string) $data['number']),
                'issue_date' => ($data['issue_date'] ?? null) ?: null,
                'expiry_date' => ($data['expiry_date'] ?? null) ?: null,
                'status' => $data['status'] ?? ProviderCredentialStatus::Active->value,
                'is_primary' => $isPrimary,
                'document_id' => $data['document_id'] ?? null,
                'notes' => $data['notes'] ?? null,
            ];

            if ($credentialId) {
                $credential = ProviderCredential::query()
                    ->where('provider_id', $provider->id)
                    ->findOrFail($credentialId);
                $credential->update($payload);
            } else {
                $credential = ProviderCredential::create($payload);
            }

            if ($type === ProviderCredentialType::License && ! $provider->credentials()->where('credential_type', ProviderCredentialType::License->value)->where('is_primary', true)->exists()) {
                $credential->update(['is_primary' => true]);
            }

            $this->syncLegacy($provider->fresh());

            return $credential->fresh();
        });
    }

    public function delete(ProviderCredential $credential): void
    {
        $provider = $credential->provider;
        $wasLicense = $credential->credential_type === ProviderCredentialType::License;
        $credential->delete();

        if ($wasLicense && $provider) {
            $next = $provider->credentials()
                ->where('credential_type', ProviderCredentialType::License->value)
                ->orderByDesc('is_primary')
                ->first();
            $next?->update(['is_primary' => true]);
        }

        if ($provider) {
            $this->syncLegacy($provider->fresh());
        }
    }

    public function syncLegacy(ProviderDetails $provider): void
    {
        $credentials = $provider->credentials()->get();

        $license = $credentials
            ->where('credential_type', ProviderCredentialType::License)
            ->sortByDesc(fn (ProviderCredential $c) => $c->is_primary)
            ->first();

        $dea = $credentials
            ->where('credential_type', ProviderCredentialType::Dea)
            ->sortByDesc(fn (ProviderCredential $c) => $c->is_primary)
            ->first();

        $cds = $credentials
            ->where('credential_type', ProviderCredentialType::Cds)
            ->sortByDesc(fn (ProviderCredential $c) => $c->is_primary)
            ->first();

        $states = $credentials
            ->pluck('state')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $provider->update([
            'license_number' => $license?->number,
            'license_state' => $license?->state,
            'dea' => $dea?->number,
            'cds_number' => $cds?->number,
            'cds_state' => $cds?->state,
            'licensed_states' => $states ?: null,
        ]);
    }

    public function syncFromDocument(Document $document, bool $enabled = true): ?ProviderCredential
    {
        if (! $enabled || ! $document->provider_id || ! $document->state) {
            return null;
        }

        $type = ProviderCredentialType::fromDocumentTypeName($document->documentType?->name);
        if (! $type) {
            return null;
        }

        $provider = $document->provider ?? ProviderDetails::find($document->provider_id);
        if (! $provider) {
            return null;
        }

        $state = UsStates::normalize($document->state);
        if (! $state) {
            return null;
        }

        $existing = ProviderCredential::query()
            ->where('provider_id', $provider->id)
            ->where('credential_type', $type->value)
            ->where('state', $state)
            ->first();

        $number = $existing?->number ?: ($document->title ?: $type->label().' '.$state);

        return $this->save($provider, [
            'credential_type' => $type->value,
            'state' => $state,
            'number' => $number,
            'issue_date' => $document->effective_date?->format('Y-m-d'),
            'expiry_date' => $document->expiry_date?->format('Y-m-d'),
            'status' => ProviderCredentialStatus::Active->value,
            'is_primary' => $existing?->is_primary ?? ($type === ProviderCredentialType::License && ! $existing),
            'document_id' => $document->id,
            'notes' => $existing?->notes,
        ], $existing?->id);
    }
}
