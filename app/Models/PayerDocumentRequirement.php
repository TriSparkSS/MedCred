<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayerDocumentRequirement extends Model
{
    protected $fillable = [
        'payer_id',
        'document_type_id',
        'state',
        'is_required',
    ];

    protected $casts = [
        'is_required' => 'boolean',
    ];

    public function payer(): BelongsTo
    {
        return $this->belongsTo(Payer::class);
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }
}
