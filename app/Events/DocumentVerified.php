<?php

namespace App\Events;

use App\Models\Document;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DocumentVerified
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Document $document,
        public ?int $adminId = null,
    ) {
    }
}
