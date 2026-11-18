<?php

namespace App\Livewire\Concerns;

use App\Services\PortalLoginService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Add Practice / Add Provider: portal logins held in component state and saved
 * with the new record (same rules, encryption and service as PortalLoginsSection).
 */
trait ManagesPendingPortalLogins
{
    public array $portalLogins = [];

    public function canManagePendingPortalLogins(): bool
    {
        return (bool) Auth::guard('admin')->user()?->can(PortalLoginService::PERMISSION);
    }

    public function addPortalLoginRow(): void
    {
        abort_unless($this->canManagePendingPortalLogins(), 403);

        $this->portalLogins[] = PortalLoginService::emptyRow();
    }

    public function removePortalLoginRow(int $index): void
    {
        unset($this->portalLogins[$index]);
        $this->portalLogins = array_values($this->portalLogins);
        $this->resetValidation();
    }

    protected function portalLoginRowRules(): array
    {
        if ($this->portalLogins === []) {
            return [];
        }

        return PortalLoginService::rules('portalLogins.*', true);
    }

    protected function portalLoginRowAttributes(): array
    {
        return [
            'portalLogins.*.portal_name' => 'portal name',
            'portalLogins.*.assigned_id' => 'assigned ID',
            'portalLogins.*.password' => 'portal password',
            'portalLogins.*.mfa_email' => 'MFA email',
            'portalLogins.*.mfa_phone' => 'MFA phone',
            'portalLogins.*.portal_url' => 'portal URL',
        ];
    }

    protected function assertCanSavePendingPortalLogins(): void
    {
        if ($this->portalLogins !== []) {
            abort_unless($this->canManagePendingPortalLogins(), 403);
        }
    }

    protected function persistPendingPortalLogins(Model $owner): void
    {
        $service = app(PortalLoginService::class);

        foreach ($this->portalLogins as $row) {
            $service->create($owner, $row);
        }
    }
}
