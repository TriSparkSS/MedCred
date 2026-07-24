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
        @set_time_limit(300);

        $maxPerFolder = app(\App\Services\GraphMailboxService::class)->isConfigured()
            ? (int) config('services.microsoft_graph.sync_batch_size', 50)
            : null;

        $emailService->syncInbox($maxPerFolder);
    }
}
