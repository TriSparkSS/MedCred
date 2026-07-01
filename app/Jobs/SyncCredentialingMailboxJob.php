<?php

namespace App\Jobs;

use App\Services\CredentialingEmailService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncCredentialingMailboxJob implements ShouldQueue
{
    use Queueable;

    public function handle(CredentialingEmailService $emailService): void
    {
        if (method_exists($emailService, 'syncInbox')) {
            $emailService->syncInbox();
        }
    }
}
