<?php

namespace App\Listeners;

use App\Events\Tasks\TaskAssigned;
use App\Events\Tasks\TaskCompleted;
use App\Events\Tasks\TaskCreated;
use App\Events\Tasks\TaskDeleted;
use App\Events\Tasks\TaskEscalated;
use App\Events\Tasks\TaskEvent;
use App\Events\Tasks\TaskNoteAdded;
use App\Events\Tasks\TaskReassigned;
use App\Events\Tasks\TaskReopened;
use App\Models\Admin;
use App\Services\TaskService;

class RecordTaskActivity
{
    public function __construct(protected TaskService $taskService)
    {
    }

    public function handleTaskCreated(TaskCreated $event): void
    {
        $this->taskService->recordActivity(
            $event->task,
            'created',
            $event->adminId,
            'Task created: ' . $event->task->title,
            $event->metadata
        );

        $this->taskService->recordAudit('task.created', $event->task, $event->adminId, $event->metadata);
    }

    public function handleTaskAssigned(TaskAssigned $event): void
    {
        $assignee = Admin::find($event->metadata['assigned_admin_id'] ?? $event->task->assigned_admin_id);

        $this->taskService->recordActivity(
            $event->task,
            'assigned',
            $event->adminId,
            'Assigned to ' . ($assignee->name ?? 'Unassigned'),
            $event->metadata
        );

        $this->taskService->recordAudit('task.assigned', $event->task, $event->adminId, $event->metadata);
    }

    public function handleTaskReassigned(TaskReassigned $event): void
    {
        $assignee = Admin::find($event->metadata['assigned_admin_id'] ?? $event->task->assigned_admin_id);
        $previous = Admin::find($event->metadata['previous_assigned_admin_id'] ?? null);

        $this->taskService->recordActivity(
            $event->task,
            'reassigned',
            $event->adminId,
            'Reassigned from ' . ($previous->name ?? 'Unassigned') . ' to ' . ($assignee->name ?? 'Unassigned'),
            $event->metadata
        );

        $this->taskService->recordAudit('task.reassigned', $event->task, $event->adminId, $event->metadata);
    }

    public function handleTaskCompleted(TaskCompleted $event): void
    {
        $this->taskService->recordActivity(
            $event->task,
            'completed',
            $event->adminId,
            'Task completed',
            $event->metadata
        );

        $this->taskService->recordAudit('task.completed', $event->task, $event->adminId, $event->metadata);
    }

    public function handleTaskReopened(TaskReopened $event): void
    {
        $this->taskService->recordActivity(
            $event->task,
            'reopened',
            $event->adminId,
            'Task reopened' . (isset($event->metadata['reason']) ? ': ' . $event->metadata['reason'] : ''),
            $event->metadata
        );

        $this->taskService->recordAudit('task.reopened', $event->task, $event->adminId, $event->metadata);
    }

    public function handleTaskEscalated(TaskEscalated $event): void
    {
        $this->taskService->recordActivity(
            $event->task,
            'escalated',
            $event->adminId,
            'Task escalated' . (isset($event->metadata['reason']) ? ': ' . $event->metadata['reason'] : ''),
            $event->metadata
        );

        $this->taskService->recordAudit('task.escalated', $event->task, $event->adminId, $event->metadata);
    }

    public function handleTaskDeleted(TaskDeleted $event): void
    {
        $this->taskService->recordAudit('task.deleted', $event->task, $event->adminId, $event->metadata);
    }

    public function handleTaskNoteAdded(TaskNoteAdded $event): void
    {
        $this->taskService->recordActivity(
            $event->task,
            'note_added',
            $event->adminId,
            'Note added',
            $event->metadata
        );
    }

    public function subscribe($events): array
    {
        return [
            TaskCreated::class => 'handleTaskCreated',
            TaskAssigned::class => 'handleTaskAssigned',
            TaskReassigned::class => 'handleTaskReassigned',
            TaskCompleted::class => 'handleTaskCompleted',
            TaskReopened::class => 'handleTaskReopened',
            TaskEscalated::class => 'handleTaskEscalated',
            TaskDeleted::class => 'handleTaskDeleted',
            TaskNoteAdded::class => 'handleTaskNoteAdded',
        ];
    }
}
