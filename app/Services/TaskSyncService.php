<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\CaseDocumentItem;
use App\Models\CredentialingCase;
use App\Models\Task;

class TaskSyncService
{
    public function __construct(protected TaskService $taskService)
    {
    }

    public function completeDocumentTask(CredentialingCase $case, CaseDocumentItem $item, ?int $adminId = null): void
    {
        $documentName = $item->documentType->name ?? 'Required document';
        $title = 'Collect: ' . $documentName;

        Task::query()
            ->where('credentialing_case_id', $case->id)
            ->where('task_type', TaskType::Document->value)
            ->where('title', $title)
            ->open()
            ->each(fn (Task $task) => $this->taskService->complete($task, $adminId ?? 1));
    }

    public function ensureDocumentTask(CredentialingCase $case, CaseDocumentItem $item, ?int $adminId = null): Task
    {
        if ($item->is_received) {
            return Task::query()
                ->where('credentialing_case_id', $case->id)
                ->where('task_type', TaskType::Document->value)
                ->where('title', 'Collect: ' . ($item->documentType->name ?? 'Required document'))
                ->first() ?? new Task;
        }

        $documentName = $item->documentType->name ?? 'Required document';
        $title = 'Collect: ' . $documentName;

        $existing = Task::query()
            ->where('credentialing_case_id', $case->id)
            ->where('task_type', TaskType::Document->value)
            ->where('title', $title)
            ->open()
            ->first();

        if ($existing) {
            return $existing;
        }

        return $this->taskService->create([
            'automation_key' => 'document:' . $case->id . ':' . $item->id,
            'task_type' => TaskType::Document->value,
            'credentialing_case_id' => $case->id,
            'title' => $title,
            'description' => 'Required for payer credentialing checklist',
            'provider_id' => $case->provider_id,
            'payer_id' => $case->payer_id,
            'assigned_admin_id' => $case->assigned_admin_id ?? $adminId,
            'due_date' => $case->next_follow_up_date ?? now()->addDays(7),
            'status' => TaskStatus::Open,
        ], $adminId ?? $case->assigned_admin_id ?? 1);
    }

    public function taskTypeLabel(string $type): string
    {
        $enum = TaskType::tryFrom($type);

        return $enum?->label() ?? ucfirst(str_replace('_', ' ', $type));
    }
}
