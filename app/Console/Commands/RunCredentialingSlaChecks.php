<?php

namespace App\Console\Commands;

use App\Services\SlaEngineService;
use Illuminate\Console\Command;

class RunCredentialingSlaChecks extends Command
{
    protected $signature = 'credentialing:run-sla-checks';

    protected $description = 'Process due SLA timers, send auto-reminders, and create escalations';

    public function handle(SlaEngineService $slaEngine): int
    {
        $results = $slaEngine->runDueChecks();

        $this->info(sprintf(
            'SLA checks complete: %d reminders, %d escalations, %d tasks.',
            $results['reminders'],
            $results['escalations'],
            $results['tasks']
        ));

        return self::SUCCESS;
    }
}
