<?php

namespace App\Jobs;

use App\Services\SlaEngineService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessSlaTimersJob implements ShouldQueue
{
    use Queueable;

    public function handle(SlaEngineService $slaEngine): void
    {
        $slaEngine->processDueTimers();
    }
}
