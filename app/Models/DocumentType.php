<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentType extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'is_active', 'is_state_specific'];

    protected $casts = [
        'is_active' => 'boolean',
        'is_state_specific' => 'boolean',
    ];
}
