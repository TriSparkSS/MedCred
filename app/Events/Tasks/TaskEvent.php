<?php

namespace App\Events\Tasks;

use App\Models\Task;

abstract class TaskEvent
{
    public function __construct(
        public Task $task,
        public ?int $adminId = null,
        public array $metadata = [],
    ) {
    }
}
