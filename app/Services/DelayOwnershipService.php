<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\CaseActivity;
use App\Models\CaseSlaTimer;
use App\Models\CredentialingCase;
use App\Models\DelayOwner;
use App\Models\DelayOverride;
use App\Models\SlaRule;
use App\Models\Status;
use App\Support\BusinessDayCalculator;

class DelayOwnershipService
{
    public function __construct(
        protected BusinessDayCalculator $businessDays,
        protected SlaEngineService $slaEngine,
    ) {}

    public function applyOnStatusChange(CredentialingCase $case, Status $status, ?int $adminId = null): void
    {
        if ($status->delay_owner_id) {
            $this->setDelayOwner($case, $status->delay_owner_id, 'Auto-assigned from status: ' . $status->name, $adminId, false);
        }

        $this->slaEngine->syncTimersForCase($case, $status);
    }

    public function overrideDelayOwner(
        CredentialingCase $case,
        int $newDelayOwnerId,
        string $reason,
        ?int $adminId = null
    ): void {
        $previousId = $case->delay_owner_id;

        DelayOverride::create([
            'credentialing_case_id' => $case->id,
            'previous_delay_owner_id' => $previousId,
            'new_delay_owner_id' => $newDelayOwnerId,
            'reason' => $reason,
            'admin_id' => $adminId,
        ]);

        $case->update(['delay_owner_id' => $newDelayOwnerId]);

        $newOwner = DelayOwner::find($newDelayOwnerId);
        $case->addActivity(
            'system',
            'Delay owner manually overridden to ' . ($newOwner->name ?? 'Unknown') . ': ' . $reason,
            $adminId
        );

        AuditLog::record('delay_owner_override', $case, $adminId, [
            'previous_delay_owner_id' => $previousId,
            'new_delay_owner_id' => $newDelayOwnerId,
            'reason' => $reason,
        ]);
    }

    protected function setDelayOwner(
        CredentialingCase $case,
        int $delayOwnerId,
        string $reason,
        ?int $adminId,
        bool $logOverride
    ): void {
        if ($case->delay_owner_id === $delayOwnerId) {
            return;
        }

        $previousId = $case->delay_owner_id;
        $case->update(['delay_owner_id' => $delayOwnerId]);

        $owner = DelayOwner::find($delayOwnerId);
        $case->addActivity('system', $reason . ' → ' . ($owner->name ?? 'Unknown'), $adminId);

        if ($logOverride) {
            AuditLog::record('delay_owner_auto', $case, $adminId, [
                'previous_delay_owner_id' => $previousId,
                'new_delay_owner_id' => $delayOwnerId,
            ]);
        }
    }

    public function shiftToDelayOwnerByName(CredentialingCase $case, string $ownerName, string $reason, ?int $adminId = null): void
    {
        $owner = DelayOwner::where('name', $ownerName)->first();
        if ($owner) {
            $this->setDelayOwner($case, $owner->id, $reason, $adminId, false);
        }
    }
}
