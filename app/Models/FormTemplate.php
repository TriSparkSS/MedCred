<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FormTemplate extends Model
{
    protected $fillable = [
        'name', 'template_key', 'version', 'description', 'file_path', 'field_mapping', 'is_active',
    ];

    protected $casts = [
        'field_mapping' => 'array',
        'is_active' => 'boolean',
    ];

    public function packetGenerations(): HasMany
    {
        return $this->hasMany(PacketGeneration::class);
    }
}
