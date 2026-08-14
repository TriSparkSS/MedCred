<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseDocumentItem extends Model
{
    protected $fillable = [
        'credentialing_case_id',
        'document_type_id',
        'document_id',
        'is_required',
        'is_received',
        'notes',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_received' => 'boolean',
    ];

    public function credentialingCase(): BelongsTo
    {
        return $this->belongsTo(CredentialingCase::class);
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
