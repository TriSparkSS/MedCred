<?php

namespace App\Events;

use App\Models\CredentialingCase;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CaseStatusChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public CredentialingCase $case,
        public ?int $oldStatusId,
        public int $newStatusId,
        public ?int $adminId = null,
        public ?string $notes = null,
    ) {
    }
}
