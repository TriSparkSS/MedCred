<?php

namespace App\Events;

use App\Models\CredentialingCase;
use App\Models\NotificationTemplate;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DocumentRequestSent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public CredentialingCase $case,
        public NotificationTemplate $template,
        public ?int $adminId = null,
    ) {
    }
}
