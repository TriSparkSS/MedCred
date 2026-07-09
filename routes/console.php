<?php

use App\Console\Commands\RunCredentialingSlaChecks;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(RunCredentialingSlaChecks::class)->dailyAt('08:00');
Schedule::command(\App\Console\Commands\ProcessTaskDueReminders::class)->dailyAt('07:30');
Schedule::command(\App\Console\Commands\SyncExpiryTasks::class)->dailyAt('06:00');
Schedule::command(\App\Console\Commands\SyncMailboxCommand::class)->everyTenMinutes();
