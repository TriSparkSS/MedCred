<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use App\Enums\ProviderStatus;

#[Fillable(['user_id', 'specialty_id', 'status', 'npi', 'practice', 'address', 'city', 'state', 'zip'])]
class ProviderDetails extends Model
{

    protected $casts = [
        'status' => ProviderStatus::class,
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function specialty()
    {
        return $this->belongsTo(Specialty::class);
    }
}
