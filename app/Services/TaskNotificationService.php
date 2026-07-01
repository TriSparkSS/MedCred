<?php

namespace App\Services;

use App\Events\Tasks\TaskAssigned;
use App\Events\Tasks\TaskEscalated;
use App\Events\Tasks\TaskReassigned;
use App\Models\AdminNotification;
use App\Models\Task;

class TaskNotificationService
{
    public function notifyAssigned(TaskAssigned|TaskReassigned $event): void
    {
        $assigneeId = $event->metadata['assigned_admin_id'] ?? $event->task->assigned_admin_id;

        if (! $assigneeId || $assigneeId === $event->adminId) {
            return;
        }

        $this->create(
            $assigneeId,
            'task_assigned',
            'Task assigned to you',
            $event->task->title,
            route('admin.tasks.kanban', ['task_id' => $event->task->id]),
            ['task_id' => $event->task->id]
        );
    }

    public function notifyEscalated(TaskEscalated $event): void
    {
        if (! $event->task->assigned_admin_id) {
            return;
        }

        $this->create(
            $event->task->assigned_admin_id,
            'task_escalated',
            'Task escalated',
            $event->task->title,
            route('admin.tasks.kanban', ['task_id' => $event->task->id]),
            ['task_id' => $event->task->id]
        );
    }

    public function sendDueTomorrowReminders(): int
    {
        $count = 0;

        Task::open()
            ->whereDate('due_date', now()->addDay())
            ->with('assignedAdmin')
            ->each(function (Task $task) use (&$count) {
                if (! $task->assigned_admin_id) {
                    return;
                }

                $this->create(
                    $task->assigned_admin_id,
                    'task_due_tomorrow',
                    'Task due tomorrow',
                    $task->title,
                    route('admin.tasks.kanban', ['task_id' => $task->id]),
                    ['task_id' => $task->id]
                );

                $count++;
            });

        return $count;
    }

    public function sendOverdueDigests(): int
    {
        $count = 0;

        Task::overdue()
            ->with('assignedAdmin')
            ->each(function (Task $task) use (&$count) {
                if (! $task->assigned_admin_id) {
                    return;
                }

                $this->create(
                    $task->assigned_admin_id,
                    'task_overdue',
                    'Task overdue',
                    $task->title,
                    route('admin.tasks.kanban', ['task_id' => $task->id]),
                    ['task_id' => $task->id]
                );

                $count++;
            });

        return $count;
    }

    public function unreadCountForAdmin(int $adminId): int
    {
        return AdminNotification::where('admin_id', $adminId)->unread()->count();
    }

    public function unreadAssignmentCountForAdmin(int $adminId): int
    {
        return AdminNotification::where('admin_id', $adminId)
            ->unread()
            ->where('type', 'task_assigned')
            ->count();
    }

    protected function create(int $adminId, string $type, string $title, ?string $message, ?string $link, array $metadata = []): AdminNotification
    {
        return AdminNotification::create([
            'admin_id' => $adminId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'link' => $link,
            'metadata' => $metadata ?: null,
        ]);
    }
}
