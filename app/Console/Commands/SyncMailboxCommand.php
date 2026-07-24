<?php

namespace App\Console\Commands;

use App\Services\CredentialingEmailService;
use Illuminate\Console\Command;

class SyncMailboxCommand extends Command
{
    protected $signature = 'mailbox:sync';

    protected $description = 'Sync the credentialing mailbox via Microsoft Graph (OAuth) or IMAP fallback';

    public function handle(CredentialingEmailService $emailService): int
    {
        @set_time_limit(300);

        $via = app(\App\Services\GraphMailboxService::class)->isConfigured()
            ? 'Microsoft Graph'
            : 'IMAP';

        $this->info("Syncing mailbox via {$via}...");

        $maxPerFolder = app(\App\Services\GraphMailboxService::class)->isConfigured()
            ? (int) config('services.microsoft_graph.sync_batch_size', 50)
            : null;

        $result = $emailService->syncInbox($maxPerFolder);

        $this->info("Imported: {$result['imported']}, Skipped: {$result['skipped']}, Errors: " . count($result['errors']));

        if (! empty($result['has_more'])) {
            $this->info('More messages remain — run mailbox:sync again or wait for the scheduler.');
        }

        foreach ($result['errors'] as $error) {
            $this->warn($error);
        }

        return self::SUCCESS;
    }
}
