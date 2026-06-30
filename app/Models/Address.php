<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'type',
    'location_name',
    'address1',
    'address2',
    'city',
    'state',
    'zip_code',
    'county',
    'country',
    'phone',
    'fax',
    'status',
])]
class Address extends Model
{
    public function addressable()
    {
        return $this->morphTo();
    }
}
