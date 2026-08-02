<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailCaseLink extends Model
{
    protected $fillable = [
        'message_id',
        'credentialing_case_id',
    ];

    public function credentialingCase(): BelongsTo
    {
        return $this->belongsTo(CredentialingCase::class);
    }

    public static function normalizeMessageId(?string $messageId): ?string
    {
        if (blank($messageId)) {
            return null;
        }

        return trim($messageId, " \t\n\r\0\x0B<>");
    }
}
