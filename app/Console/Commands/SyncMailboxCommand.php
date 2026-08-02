<?php

namespace App\Console\Commands;

use App\Services\GraphMailboxService;
use Illuminate\Console\Command;

class SyncMailboxCommand extends Command
{
    protected $signature = 'mailbox:sync';

    protected $description = 'Clear the Microsoft Graph mailbox cache so Email Center loads fresh data';

    public function handle(GraphMailboxService $graph): int
    {
        if (! $graph->isConfigured()) {
            $this->error('Microsoft Graph is not configured. Set GRAPH_* env vars.');

            return self::FAILURE;
        }

        $graph->clearCache();
        $this->info('Graph mailbox cache cleared. Email Center will fetch live data on the next request.');

        return self::SUCCESS;
    }
}
