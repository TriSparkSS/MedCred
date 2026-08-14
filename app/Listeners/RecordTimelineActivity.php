<?php

namespace App\Listeners;

use App\Events\CaseStatusChanged;
use App\Events\DelayOwnerChanged;
use App\Events\DocumentVerified;
use App\Models\AuditLog;
use App\Models\CaseActivity;
use App\Models\DelayOwnerHistory;
use App\Models\Status;

class RecordTimelineActivity
{
    public function handleCaseStatusChanged(CaseStatusChanged $event): void
    {
        $status = Status::find($event->newStatusId);

        CaseActivity::create([
            'credentialing_case_id' => $event->case->id,
            'activity_type' => 'status_change',
            'admin_id' => $event->adminId,
            'summary' => 'Status changed to ' . ($status->name ?? 'Unknown') . ($event->notes ? ": {$event->notes}" : ''),
        ]);

        AuditLog::record('case.status_changed', $event->case, $event->adminId, [
            'old_status_id' => $event->oldStatusId,
            'new_status_id' => $event->newStatusId,
        ]);
    }

    public function handleDelayOwnerChanged(DelayOwnerChanged $event): void
    {
        DelayOwnerHistory::create([
            'credentialing_case_id' => $event->case->id,
            'delay_owner_id' => $event->newDelayOwnerId,
            'previous_delay_owner_id' => $event->previousDelayOwnerId,
            'source' => $event->source,
            'reason' => $event->reason,
            'changed_by_admin_id' => $event->adminId,
        ]);

        CaseActivity::create([
            'credentialing_case_id' => $event->case->id,
            'activity_type' => 'delay_change',
            'admin_id' => $event->adminId,
            'summary' => 'Delay owner updated' . ($event->reason ? ": {$event->reason}" : ''),
        ]);
    }

    public function handleDocumentVerified(DocumentVerified $event): void
    {
        if ($event->document->credentialing_case_id) {
            CaseActivity::create([
                'credentialing_case_id' => $event->document->credentialing_case_id,
                'activity_type' => 'document',
                'admin_id' => $event->adminId,
                'summary' => 'Document verified: ' . $event->document->title,
            ]);
        }

        AuditLog::record('document.verified', $event->document, $event->adminId);
    }

    public function subscribe($events): array
    {
        return [
            CaseStatusChanged::class => 'handleCaseStatusChanged',
            DelayOwnerChanged::class => 'handleDelayOwnerChanged',
            DocumentVerified::class => 'handleDocumentVerified',
        ];
    }
}
