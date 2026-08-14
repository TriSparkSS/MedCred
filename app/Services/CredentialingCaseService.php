<?php

namespace App\Services;

use App\Events\CaseStatusChanged;
use App\Models\CredentialingCase;
use App\Models\Status;
use App\Repositories\Contracts\CredentialingCaseRepositoryInterface;
use Illuminate\Support\Facades\DB;

class CredentialingCaseService
{
    public function __construct(
        protected CredentialingCaseRepositoryInterface $cases,
        protected DelayOwnershipService $delayService,
        protected SlaEngineService $slaEngine,
    ) {
    }

    public function create(array $data, ?int $adminId = null, bool $allowDuplicate = false): CredentialingCase
    {
        if (! $allowDuplicate) {
            $duplicate = $this->cases->findOpenDuplicate([
                'provider_id' => $data['provider_id'],
                'practice_id' => $data['practice_id'],
                'payer_id' => $data['payer_id'],
                'state' => $data['state'] ?? null,
                'case_type_id' => $data['case_type_id'] ?? null,
                'provider_practice_location_id' => $data['provider_practice_location_id'] ?? null,
            ]);

            if ($duplicate) {
                throw new \InvalidArgumentException(
                    "An open case already exists ({$duplicate->case_number}). Confirm to create anyway."
                );
            }
        }

        return DB::transaction(function () use ($data, $adminId) {
            $practice = \App\Models\Practice::find($data['practice_id'] ?? null);
            if (! $practice || ! preg_match('/^[A-Z]{3}$/', strtoupper(trim((string) $practice->client_code)))) {
                throw new \InvalidArgumentException(
                    'This practice needs a 3-letter client code before creating a case. Edit the practice and set Client Code.'
                );
            }

            $case = CredentialingCase::create([
                ...$data,
                'last_action_at' => now(),
            ]);

            $case->seedDocumentChecklist();
            $case->recordStatusChange($data['status_id'], $adminId, 'Case created');
            $this->slaEngine->startTimersForCase($case->fresh());

            CaseStatusChanged::dispatch($case->fresh(), null, (int) $data['status_id'], $adminId, 'Case created');

            return $case->fresh();
        });
    }

    public function changeStatus(CredentialingCase $case, int $statusId, ?int $adminId = null, ?string $notes = null): CredentialingCase
    {
        $oldStatusId = $case->status_id;

        if ($oldStatusId === $statusId) {
            return $case;
        }

        $status = Status::findOrFail($statusId);

        $case->update([
            'status_id' => $statusId,
            'delay_owner_id' => $status->delay_owner_id ?? $case->delay_owner_id,
            'last_action_at' => now(),
        ]);

        $case->statusHistories()->create([
            'status_id' => $statusId,
            'delay_owner_id' => $status->delay_owner_id,
            'changed_by_admin_id' => $adminId,
            'notes' => $notes,
        ]);

        CaseStatusChanged::dispatch($case->fresh(), $oldStatusId, $statusId, $adminId, $notes);

        $this->delayService->applyOnStatusChange($case->fresh(), $status, $adminId);

        return $case->fresh();
    }

    public function updateInline(CredentialingCase $case, array $fields, ?int $adminId = null): CredentialingCase
    {
        $allowed = ['status_id', 'assigned_admin_id', 'priority_id', 'next_follow_up_date', 'delay_owner_id'];
        $updates = array_intersect_key($fields, array_flip($allowed));

        if (isset($updates['status_id']) && $updates['status_id'] != $case->status_id) {
            return $this->changeStatus($case, (int) $updates['status_id'], $adminId);
        }

        $case->update([...$updates, 'last_action_at' => now()]);

        if ($case->wasChanged()) {
            $case->addActivity('system', 'Case fields updated', $adminId);
        }

        return $case->fresh();
    }

    public function readinessWarnings(CredentialingCase $case): array
    {
        $warnings = [];
        $checklist = $case->checklist_completion;

        if ($checklist['total'] > 0 && $checklist['received'] < $checklist['total']) {
            $warnings[] = "{$checklist['total']} checklist items; {$checklist['received']} received.";
        }

        return $warnings;
    }
}
