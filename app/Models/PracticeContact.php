<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PracticeContact extends Model
{
    protected $fillable = [
        'practice_id',
        'name',
        'title',
        'email',
        'phone',
        'fax',
        'is_primary',
        'notes',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function practice(): BelongsTo
    {
        return $this->belongsTo(Practice::class);
    }
}
