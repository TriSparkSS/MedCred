<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Specialty extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
    ];

    protected $casts = [
        'code' => 'string',
    ];

    public function providerDetails()
    {
        return $this->hasMany(ProviderDetails::class);
    }
}
