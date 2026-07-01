<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PacketGeneration extends Model
{
    protected $fillable = [
        'credentialing_case_id',
        'form_template_id',
        'generated_by_admin_id',
        'file_path',
        'form_version',
        'included_document_ids',
        'metadata',
    ];

    protected $casts = [
        'included_document_ids' => 'array',
        'metadata' => 'array',
    ];

    public function credentialingCase(): BelongsTo
    {
        return $this->belongsTo(CredentialingCase::class);
    }

    public function formTemplate(): BelongsTo
    {
        return $this->belongsTo(FormTemplate::class);
    }

    public function generatedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'generated_by_admin_id');
    }
}
