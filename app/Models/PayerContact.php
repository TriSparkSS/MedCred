<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayerContact extends Model
{
    protected $fillable = ['payer_id', 'name', 'email', 'phone', 'contact_type', 'is_primary'];

    protected $casts = ['is_primary' => 'boolean'];

    public function payer(): BelongsTo
    {
        return $this->belongsTo(Payer::class);
    }
}
