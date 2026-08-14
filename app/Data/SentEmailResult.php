<?php

namespace App\Data;

class SentEmailResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $messageId,
        public readonly ?string $errorMessage = null,
        public readonly ?int $caseId = null,
    ) {}

    public function failed(): bool
    {
        return ! $this->success;
    }
}
