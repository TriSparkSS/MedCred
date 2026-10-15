<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentType extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'is_active',
        'is_state_specific',
        'requires_expiry',
        'expiry_warning_days',
        'accepted_mime_types',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_state_specific' => 'boolean',
        'requires_expiry' => 'boolean',
        'expiry_warning_days' => 'integer',
        'accepted_mime_types' => 'array',
    ];
}
