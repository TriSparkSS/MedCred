<?php

namespace App\Jobs;

use App\Services\GraphMailboxService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncCredentialingMailboxJob implements ShouldQueue
{
    use Queueable;

    public function handle(GraphMailboxService $graph): void
    {
        if ($graph->isConfigured()) {
            $graph->clearCache();
        }
    }
}
