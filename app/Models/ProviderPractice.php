<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ProviderPractice extends Pivot
{
    protected $table = 'provider_practice';

    public $incrementing = true;

    protected $fillable = [
        'provider_id',
        'practice_id',
        'primary_flag',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'primary_flag' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function provider()
    {
        return $this->belongsTo(ProviderDetails::class, 'provider_id');
    }

    public function practice()
    {
        return $this->belongsTo(Practice::class);
    }
}
