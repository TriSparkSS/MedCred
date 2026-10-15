<?php

namespace App\Listeners;

use App\Events\Tasks\TaskAssigned;
use App\Events\Tasks\TaskEscalated;
use App\Events\Tasks\TaskReassigned;
use App\Services\TaskNotificationService;

class TaskNotificationListener
{
    public function __construct(protected TaskNotificationService $notifications)
    {
    }

    public function handleTaskAssigned(TaskAssigned $event): void
    {
        $this->notifications->notifyAssigned($event);
    }

    public function handleTaskReassigned(TaskReassigned $event): void
    {
        $this->notifications->notifyAssigned($event);
    }

    public function handleTaskEscalated(TaskEscalated $event): void
    {
        $this->notifications->notifyEscalated($event);
    }
}
