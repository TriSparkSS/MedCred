<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Events\Tasks\TaskAssigned;
use App\Events\Tasks\TaskCompleted;
use App\Events\Tasks\TaskCreated;
use App\Events\Tasks\TaskDeleted;
use App\Events\Tasks\TaskEscalated;
use App\Events\Tasks\TaskNoteAdded;
use App\Events\Tasks\TaskReassigned;
use App\Events\Tasks\TaskReopened;
use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\CaseActivity;
use App\Models\CredentialingCase;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskAttachment;
use App\Models\TaskNote;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class TaskService
{
    public function create(array $data, int $adminId): Task
    {
        $this->validateAssignee($data['assigned_admin_id'] ?? null);

        $case = isset($data['credentialing_case_id'])
            ? CredentialingCase::find($data['credentialing_case_id'])
            : null;

        if ($case && empty($data['provider_id'])) {
            $data['provider_id'] = $case->provider_id;
        }

        if ($case && empty($data['payer_id'])) {
            $data['payer_id'] = $case->payer_id;
        }

        $task = Task::create([
            ...$data,
            'status' => $data['status'] ?? TaskStatus::Open,
            'created_by_admin_id' => $adminId,
            'assigned_by_admin_id' => $data['assigned_admin_id'] ? $adminId : null,
        ]);

        event(new TaskCreated($task, $adminId));

        if ($task->assigned_admin_id) {
            event(new TaskAssigned($task, $adminId, [
                'assigned_admin_id' => $task->assigned_admin_id,
            ]));
        }

        return $task->fresh();
    }

    public function update(Task $task, array $data, int $adminId): Task
    {
        if (isset($data['assigned_admin_id'])) {
            $this->validateAssignee($data['assigned_admin_id']);
        }

        $previous = $task->only(['title', 'description', 'priority_id', 'due_date', 'follow_up_date', 'credentialing_case_id', 'provider_id', 'payer_id']);

        $case = isset($data['credentialing_case_id'])
            ? CredentialingCase::find($data['credentialing_case_id'])
            : null;

        if ($case) {
            $data['provider_id'] = $data['provider_id'] ?? $case->provider_id;
            $data['payer_id'] = $data['payer_id'] ?? $case->payer_id;
        }

        $task->update($data);

        $this->recordActivity($task, 'updated', $adminId, 'Task updated', [
            'previous' => $previous,
            'new' => $task->only(array_keys($previous)),
        ]);

        AuditLog::record('task.updated', $task, $adminId, [
            'previous' => $previous,
            'new' => $task->only(array_keys($previous)),
        ]);

        return $task->fresh();
    }

    public function assign(Task $task, ?int $assigneeId, int $adminId): Task
    {
        $this->validateAssignee($assigneeId);

        $previous = $task->assigned_admin_id;

        $task->update([
            'assigned_admin_id' => $assigneeId,
            'assigned_by_admin_id' => $assigneeId ? $adminId : null,
        ]);

        event(new TaskAssigned($task->fresh(), $adminId, [
            'previous_assigned_admin_id' => $previous,
            'assigned_admin_id' => $assigneeId,
        ]));

        return $task->fresh();
    }

    public function reassign(Task $task, ?int $assigneeId, int $adminId, ?string $reason = null): Task
    {
        if (! $reason) {
            throw ValidationException::withMessages(['reason' => 'A reason is required when reassigning a task.']);
        }

        $this->validateAssignee($assigneeId);

        $previous = $task->assigned_admin_id;

        $task->update([
            'assigned_admin_id' => $assigneeId,
            'assigned_by_admin_id' => $assigneeId ? $adminId : null,
        ]);

        event(new TaskReassigned($task->fresh(), $adminId, [
            'previous_assigned_admin_id' => $previous,
            'assigned_admin_id' => $assigneeId,
            'reason' => $reason,
        ]));

        return $task->fresh();
    }

    public function complete(Task $task, int $adminId): Task
    {
        $previousStatus = $task->status;

        $task->update([
            'completed_at' => now(),
            'status' => TaskStatus::Completed,
        ]);

        event(new TaskCompleted($task->fresh(), $adminId, [
            'previous_status' => $previousStatus?->value ?? $previousStatus,
        ]));

        return $task->fresh();
    }

    public function reopen(Task $task, int $adminId, ?string $reason = null): Task
    {
        $previousStatus = $task->status;

        $task->update([
            'completed_at' => null,
            'status' => TaskStatus::Open,
            'is_escalated' => false,
        ]);

        event(new TaskReopened($task->fresh(), $adminId, [
            'previous_status' => $previousStatus?->value ?? $previousStatus,
            'reason' => $reason,
        ]));

        return $task->fresh();
    }

    public function escalate(Task $task, int $adminId, ?string $reason = null): Task
    {
        $task->update([
            'is_escalated' => true,
            'status' => TaskStatus::Escalated,
            'escalated_at' => now(),
            'escalated_by_admin_id' => $adminId,
        ]);

        event(new TaskEscalated($task->fresh(), $adminId, [
            'reason' => $reason,
        ]));

        return $task->fresh();
    }

    public function delete(Task $task, int $adminId): void
    {
        event(new TaskDeleted($task, $adminId));

        $task->delete();
    }

    public function addNote(Task $task, string $body, int $adminId): TaskNote
    {
        $note = TaskNote::create([
            'task_id' => $task->id,
            'admin_id' => $adminId,
            'body' => $body,
        ]);

        event(new TaskNoteAdded($task->fresh(), $adminId, [
            'note_id' => $note->id,
            'body' => $body,
        ]));

        return $note;
    }

    public function attachFile(Task $task, UploadedFile $file, int $adminId): TaskAttachment
    {
        $path = $file->store('task-attachments/' . $task->id, 'local');

        $attachment = TaskAttachment::create([
            'task_id' => $task->id,
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'uploaded_by_admin_id' => $adminId,
        ]);

        $this->recordActivity($task, 'attachment_added', $adminId, 'Attachment added: ' . $file->getClientOriginalName(), [
            'attachment_id' => $attachment->id,
        ]);

        AuditLog::record('task.attachment_added', $task, $adminId, [
            'attachment_id' => $attachment->id,
            'original_name' => $file->getClientOriginalName(),
        ]);

        return $attachment;
    }

    public function ensureTask(string $automationKey, CredentialingCase $case, array $defaults, ?int $adminId = null): Task
    {
        $existing = Task::query()
            ->where('automation_key', $automationKey)
            ->where('credentialing_case_id', $case->id)
            ->open()
            ->first();

        if ($existing) {
            return $existing;
        }

        return $this->create([
            'automation_key' => $automationKey,
            'credentialing_case_id' => $case->id,
            'provider_id' => $case->provider_id,
            'payer_id' => $case->payer_id,
            'assigned_admin_id' => $defaults['assigned_admin_id'] ?? $case->assigned_admin_id,
            'title' => $defaults['title'],
            'description' => $defaults['description'] ?? null,
            'task_type' => $defaults['task_type'] ?? 'manual',
            'due_date' => $defaults['due_date'] ?? null,
            'follow_up_date' => $defaults['follow_up_date'] ?? null,
            'priority_id' => $defaults['priority_id'] ?? null,
            'status' => $defaults['status'] ?? TaskStatus::Open,
        ], $adminId ?? $case->assigned_admin_id ?? 1);
    }

    public function recordActivity(Task $task, string $type, ?int $adminId, string $summary, array $metadata = []): TaskActivity
    {
        return DB::transaction(function () use ($task, $type, $adminId, $summary, $metadata) {
            $activity = TaskActivity::create([
                'task_id' => $task->id,
                'activity_type' => $type,
                'admin_id' => $adminId,
                'summary' => $summary,
                'metadata' => $metadata ?: null,
            ]);

            if ($task->credentialing_case_id) {
                CaseActivity::create([
                    'credentialing_case_id' => $task->credentialing_case_id,
                    'activity_type' => 'task',
                    'admin_id' => $adminId,
                    'summary' => $summary,
                    'metadata' => array_merge($metadata, ['task_id' => $task->id]),
                ]);
            }

            return $activity;
        });
    }

    public function recordAudit(string $action, Task $task, ?int $adminId, array $metadata = []): void
    {
        AuditLog::record($action, $task, $adminId, $metadata);
    }

    protected function validateAssignee(?int $assigneeId): void
    {
        if ($assigneeId === null) {
            return;
        }

        if (! Admin::whereKey($assigneeId)->exists()) {
            throw ValidationException::withMessages(['assigned_admin_id' => 'Invalid assignee.']);
        }
    }
}
