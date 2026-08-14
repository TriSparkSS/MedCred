<?php

namespace App\Console\Commands;

use App\Services\FollowUpEngine;
use Illuminate\Console\Command;

class SyncExpiryTasks extends Command
{
    protected $signature = 'tasks:sync-expiry-tasks';

    protected $description = 'Create renewal tasks for documents expiring within 30 days';

    public function handle(FollowUpEngine $engine): int
    {
        $count = $engine->syncExpiryTasks();

        $this->info("Created {$count} expiry task(s).");

        return self::SUCCESS;
    }
}
