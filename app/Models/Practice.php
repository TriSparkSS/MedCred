<?php

namespace App\Models;

use App\Enums\PracticeStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'user_id',
    'legal_name',
    'dba_name',
    'ein_tin',
    'group_npi',
    'taxonomy_code',
    'phone',
    'fax',
    'email',
    'website',
    'status',
])]
class Practice extends Model
{
    protected $casts = [
        'status' => PracticeStatus::class,
    ];

    protected static function booted(): void
    {
        static::deleting(function (Practice $practice) {
            $practice->addresses()->delete();
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function addresses()
    {
        return $this->morphMany(Address::class, 'addressable');
    }

    public function primaryAddress()
    {
        return $this->morphOne(Address::class, 'addressable')->oldestOfMany();
    }
}
