<?php

namespace App\Livewire\Admin\Provider\Concerns;

use App\Enums\ProviderCredentialType;
use App\Models\ProviderDetails;
use App\Services\ProviderCredentialService;
use App\Support\UsStates;

trait ManagesProviderCredentialRows
{
    public array $credentialRows = [];

    public function addCredentialRow(): void
    {
        $this->credentialRows[] = $this->emptyCredentialRow(false);
    }

    public function removeCredentialRow(int $index): void
    {
        unset($this->credentialRows[$index]);
        $this->credentialRows = array_values($this->credentialRows);

        if ($this->credentialRows === []) {
            $this->credentialRows = [$this->emptyCredentialRow(true)];
        }
    }

    protected function emptyCredentialRow(bool $primary): array
    {
        return [
            'id' => null,
            'credential_type' => ProviderCredentialType::License->value,
            'state' => '',
            'number' => '',
            'expiry_date' => '',
            'is_primary' => $primary,
        ];
    }

    protected function loadCredentialRows(ProviderDetails $provider): void
    {
        $rows = $provider->credentials()
            ->orderBy('credential_type')
            ->orderBy('state')
            ->get()
            ->map(fn ($credential) => [
                'id' => $credential->id,
                'credential_type' => $credential->credential_type->value,
                'state' => $credential->state,
                'number' => $credential->number,
                'expiry_date' => $credential->expiry_date?->format('Y-m-d') ?? '',
                'is_primary' => (bool) $credential->is_primary,
            ])
            ->all();

        $this->credentialRows = $rows !== [] ? $rows : [$this->emptyCredentialRow(true)];
    }

    protected function persistCredentialRows(ProviderDetails $provider, bool $deleteMissing = false): void
    {
        $service = app(ProviderCredentialService::class);
        $keepIds = [];
        $savedPrimary = $provider->credentials()
            ->where('credential_type', ProviderCredentialType::License->value)
            ->where('is_primary', true)
            ->exists();

        foreach ($this->credentialRows as $row) {
            $number = trim((string) ($row['number'] ?? ''));
            $state = UsStates::normalize($row['state'] ?? null);
            $type = $row['credential_type'] ?? ProviderCredentialType::License->value;

            if ($number === '' || $state === null) {
                continue;
            }

            $id = filled($row['id'] ?? null) ? (int) $row['id'] : null;
            $isLicense = $type === ProviderCredentialType::License->value;

            $saved = $service->save($provider, [
                'credential_type' => $type,
                'state' => $state,
                'number' => $number,
                'expiry_date' => ($row['expiry_date'] ?? null) ?: null,
                'is_primary' => (bool) ($row['is_primary'] ?? false) || (! $savedPrimary && $isLicense),
            ], $id);

            if ($isLicense) {
                $savedPrimary = true;
            }

            $keepIds[] = $saved->id;
        }

        if (! $deleteMissing) {
            return;
        }

        $provider->credentials()
            ->when($keepIds !== [], fn ($query) => $query->whereNotIn('id', $keepIds))
            ->when($keepIds === [], fn ($query) => $query)
            ->get()
            ->each(fn ($credential) => $service->delete($credential));
    }
}
