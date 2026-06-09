<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['user_id', 'specialty_id', 'status', 'npi', 'practice', 'address', 'city', 'state', 'zip'])]
class ProviderDetails extends Model
{

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
