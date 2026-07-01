<?php

namespace App\Console\Commands;

use App\Services\FollowUpEngine;
use App\Services\TaskNotificationService;
use Illuminate\Console\Command;

class ProcessTaskDueReminders extends Command
{
    protected $signature = 'tasks:process-due-reminders';

    protected $description = 'Send due-tomorrow and overdue task notifications';

    public function handle(TaskNotificationService $notifications, FollowUpEngine $engine): int
    {
        $dueTomorrow = $notifications->sendDueTomorrowReminders();
        $overdue = $notifications->sendOverdueDigests();
        $payerNoResponse = $engine->processPayerNoResponseCases();

        $this->info("Due tomorrow: {$dueTomorrow}, Overdue: {$overdue}, Payer no-response: {$payerNoResponse}");

        return self::SUCCESS;
    }
}
