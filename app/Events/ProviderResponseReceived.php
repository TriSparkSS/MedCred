<?php

namespace App\Events;

use App\Models\CredentialingCase;
use App\Models\EmailMessage;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProviderResponseReceived
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public EmailMessage $message,
        public CredentialingCase $case,
        public ?int $adminId = null,
    ) {
    }
}
