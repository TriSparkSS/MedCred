<?php

namespace App\Events;

use App\Models\CredentialingCase;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DelayOwnerChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public CredentialingCase $case,
        public ?int $previousDelayOwnerId,
        public ?int $newDelayOwnerId,
        public string $source,
        public ?string $reason = null,
        public ?int $adminId = null,
    ) {
    }
}
