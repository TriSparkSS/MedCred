<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Status extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'is_active', 'sort_order', 'dashboard_category', 'delay_owner_id'];

    public function delayOwner()
    {
        return $this->belongsTo(DelayOwner::class);
    }
}
