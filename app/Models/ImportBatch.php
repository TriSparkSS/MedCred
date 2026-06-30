<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportBatch extends Model
{
    protected $fillable = [
        'type',
        'filename',
        'total_rows',
        'success_rows',
        'failed_rows',
        'errors',
        'admin_id',
    ];

    protected $casts = [
        'errors' => 'array',
    ];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }
}
