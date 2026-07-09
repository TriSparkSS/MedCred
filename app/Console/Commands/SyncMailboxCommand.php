<?php

namespace App\Console\Commands;

use App\Services\CredentialingEmailService;
use Illuminate\Console\Command;

class SyncMailboxCommand extends Command
{
    protected $signature = 'mailbox:sync';

    protected $description = 'Sync the credentialing mailbox via IMAP';

    public function handle(CredentialingEmailService $emailService): int
    {
        $result = $emailService->syncInbox();

        $this->info("Imported: {$result['imported']}, Skipped: {$result['skipped']}, Errors: " . count($result['errors']));

        foreach ($result['errors'] as $error) {
            $this->warn($error);
        }

        return self::SUCCESS;
    }
}
