<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Country extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'code_3',
        'phone_code',
        'phone_number_length',
        'status',
    ];

    public function regions()
    {
        return $this->hasMany(Region::class, 'country_id');
    }
}
