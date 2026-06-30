<?php

namespace App\Services;

use App\Models\CaseDocumentItem;
use App\Models\CredentialingCase;
use App\Models\Task;

class TaskSyncService
{
    public function completeDocumentTask(CredentialingCase $case, CaseDocumentItem $item): void
    {
        $documentName = $item->documentType->name ?? 'Required document';
        $title = 'Collect: ' . $documentName;

        Task::query()
            ->where('credentialing_case_id', $case->id)
            ->where('task_type', 'document')
            ->where('title', $title)
            ->open()
            ->each(fn (Task $task) => $task->markComplete());
    }

    public function ensureDocumentTask(CredentialingCase $case, CaseDocumentItem $item, ?int $adminId = null): void
    {
        if ($item->is_received) {
            return;
        }

        $documentName = $item->documentType->name ?? 'Required document';

        Task::firstOrCreate(
            [
                'task_type' => 'document',
                'credentialing_case_id' => $case->id,
                'title' => 'Collect: ' . $documentName,
            ],
            [
                'description' => 'Required for payer credentialing checklist',
                'provider_id' => $case->provider_id,
                'assigned_admin_id' => $case->assigned_admin_id ?? $adminId,
                'created_by_admin_id' => $adminId,
                'due_date' => $case->next_follow_up_date ?? now()->addDays(7),
            ]
        );
    }

    public function taskTypeLabel(string $type): string
    {
        return match ($type) {
            'document' => 'Document Request',
            'follow_up' => 'Follow-up',
            'sla' => 'Payer Follow-up',
            'escalation' => 'Escalation',
            'expiry' => 'Expiry',
            'manual' => 'Manual',
            default => ucfirst(str_replace('_', ' ', $type)),
        };
    }
}
