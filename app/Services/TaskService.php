<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Enums\TaskType;
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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class TaskService
{
    public function __construct(
        protected AdminScopeService $scope
    ) {}

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

    public function markInProgress(Task $task, int $adminId): Task
    {
        $previousStatus = $task->status;

        $task->update([
            'status' => TaskStatus::InProgress,
            'completed_at' => null,
        ]);

        $this->recordActivity($task, 'status_changed', $adminId, 'Task marked in progress', [
            'previous_status' => $previousStatus?->value ?? $previousStatus,
        ]);

        AuditLog::record('task.in_progress', $task, $adminId, [
            'previous_status' => $previousStatus?->value ?? $previousStatus,
        ]);

        return $task->fresh();
    }

    public function cancel(Task $task, int $adminId): Task
    {
        $previousStatus = $task->status;

        $task->update([
            'status' => TaskStatus::Cancelled,
            'completed_at' => null,
            'is_escalated' => false,
        ]);

        $this->recordActivity($task, 'cancelled', $adminId, 'Task cancelled', [
            'previous_status' => $previousStatus?->value ?? $previousStatus,
        ]);

        AuditLog::record('task.cancelled', $task, $adminId, [
            'previous_status' => $previousStatus?->value ?? $previousStatus,
        ]);

        return $task->fresh();
    }

    public function bulkAssign(iterable $tasks, ?int $assigneeId, int $adminId): int
    {
        $actor = Admin::find($adminId);

        if (! $actor) {
            return 0;
        }

        return DB::transaction(function () use ($tasks, $assigneeId, $adminId, $actor) {
            $count = 0;

            foreach ($tasks as $task) {
                if (! $task instanceof Task) {
                    continue;
                }

                if (! Gate::forUser($actor)->allows('assign', $task)) {
                    continue;
                }

                $this->assign($task, $assigneeId, $adminId);
                $count++;
            }

            return $count;
        });
    }

    public function bulkUpdateStatus(iterable $tasks, string $status, int $adminId, Admin $actor): int
    {
        $status = strtolower(trim($status));

        if (! in_array($status, ['in_progress', 'completed', 'cancelled', 'escalated', 'open'], true)) {
            return 0;
        }

        return DB::transaction(function () use ($tasks, $status, $adminId, $actor) {
            $count = 0;

            foreach ($tasks as $task) {
                if (! $task instanceof Task) {
                    continue;
                }

                if (! $this->applyBulkStatus($task, $status, $adminId, $actor)) {
                    continue;
                }

                $count++;
            }

            return $count;
        });
    }

    public function createFollowUpsForCases(array $caseIds, array $payload, int $adminId): int
    {
        $actor = Admin::find($adminId);

        if (! $actor || ! $actor->can('admin.tasks.manage')) {
            return 0;
        }

        $caseIds = array_values(array_unique(array_filter(array_map('intval', $caseIds))));
        $caseIds = array_slice($caseIds, 0, 50);

        if ($caseIds === []) {
            return 0;
        }

        $title = trim((string) ($payload['title'] ?? ''));

        if ($title === '') {
            throw ValidationException::withMessages(['title' => 'A follow-up title is required.']);
        }

        $occurrences = max(1, min(12, (int) ($payload['occurrences'] ?? 1)));
        $intervalDays = $occurrences > 1
            ? max(1, min(90, (int) ($payload['interval_days'] ?? 7)))
            : 0;
        $start = ! empty($payload['due_date'])
            ? Carbon::parse($payload['due_date'])->startOfDay()
            : now()->startOfDay();
        $assigneeId = isset($payload['assigned_admin_id']) && $payload['assigned_admin_id'] !== ''
            ? (int) $payload['assigned_admin_id']
            : null;
        $taskType = $payload['task_type'] ?? TaskType::FollowUp->value;
        $syncCaseFollowUp = (bool) ($payload['sync_case_follow_up'] ?? false);
        $description = $payload['description'] ?? null;

        $this->validateAssignee($assigneeId);

        return DB::transaction(function () use (
            $caseIds,
            $actor,
            $adminId,
            $title,
            $occurrences,
            $intervalDays,
            $start,
            $assigneeId,
            $taskType,
            $syncCaseFollowUp,
            $description,
        ) {
            $created = 0;
            $cases = CredentialingCase::query()->whereIn('id', $caseIds)->get();

            foreach ($cases as $case) {
                if (! $this->scope->canAccessCase($actor, $case)) {
                    continue;
                }

                for ($i = 0; $i < $occurrences; $i++) {
                    $due = $start->copy()->addDays($i * $intervalDays);
                    $label = $occurrences > 1 ? $title.' ('.($i + 1).'/'.$occurrences.')' : $title;

                    $this->create([
                        'title' => $label,
                        'description' => $description ?? 'Follow-up for '.$case->case_number,
                        'credentialing_case_id' => $case->id,
                        'provider_id' => $case->provider_id,
                        'payer_id' => $case->payer_id,
                        'assigned_admin_id' => $assigneeId,
                        'due_date' => $due->toDateString(),
                        'follow_up_date' => $due->toDateString(),
                        'task_type' => $taskType,
                    ], $adminId);

                    $created++;
                }

                if ($syncCaseFollowUp) {
                    $case->update(['next_follow_up_date' => $start->toDateString()]);
                }
            }

            return $created;
        });
    }

    protected function applyBulkStatus(Task $task, string $status, int $adminId, Admin $actor): bool
    {
        return match ($status) {
            'in_progress' => $this->applyIfAllowed(
                $actor,
                'update',
                $task,
                fn () => ! $task->isCompleted() && $task->status !== TaskStatus::InProgress && $task->status !== TaskStatus::Cancelled,
                fn () => $this->markInProgress($task, $adminId),
            ),
            'completed' => $this->applyIfAllowed(
                $actor,
                'update',
                $task,
                fn () => ! $task->isCompleted() && $task->status !== TaskStatus::Cancelled,
                fn () => $this->complete($task, $adminId),
            ),
            'cancelled' => $this->applyIfAllowed(
                $actor,
                'update',
                $task,
                fn () => $task->status !== TaskStatus::Cancelled && ! $task->isCompleted(),
                fn () => $this->cancel($task, $adminId),
            ),
            'escalated' => $this->applyIfAllowed(
                $actor,
                'escalate',
                $task,
                fn () => ! $task->isCompleted() && $task->status !== TaskStatus::Cancelled,
                fn () => $this->escalate($task, $adminId),
            ),
            'open' => $this->applyIfAllowed(
                $actor,
                'reopen',
                $task,
                fn () => $task->isCompleted() || $task->status === TaskStatus::Cancelled,
                fn () => $this->reopen($task, $adminId),
            ),
            default => false,
        };
    }

    protected function applyIfAllowed(Admin $actor, string $ability, Task $task, callable $eligible, callable $action): bool
    {
        if (! Gate::forUser($actor)->allows($ability, $task) || ! $eligible()) {
            return false;
        }

        $action();

        return true;
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
        $path = $file->store('task-attachments/'.$task->id, 'local');

        $attachment = TaskAttachment::create([
            'task_id' => $task->id,
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'uploaded_by_admin_id' => $adminId,
        ]);

        $this->recordActivity($task, 'attachment_added', $adminId, 'Attachment added: '.$file->getClientOriginalName(), [
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
