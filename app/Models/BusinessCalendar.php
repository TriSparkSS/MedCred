<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BusinessCalendar extends Model
{
    protected $fillable = ['name', 'exclude_weekends', 'is_default', 'is_active'];

    protected $casts = [
        'exclude_weekends' => 'boolean',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function holidays(): HasMany
    {
        return $this->hasMany(Holiday::class);
    }

    public static function default(): ?self
    {
        return static::where('is_default', true)->where('is_active', true)->first()
            ?? static::where('is_active', true)->first();
    }
}
