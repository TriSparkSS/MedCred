<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Location extends Model
{
    protected $fillable = [
        'practice_id',
        'name',
        'address1',
        'address2',
        'city',
        'state',
        'zip_code',
        'county',
        'country',
        'phone',
        'fax',
        'taxonomy_code',
        'npi',
        'status',
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function practice(): BelongsTo
    {
        return $this->belongsTo(Practice::class);
    }
}
