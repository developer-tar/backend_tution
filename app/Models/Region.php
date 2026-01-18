<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Region extends Model
{
    use SoftDeletes;
    protected $fillable = ['name', 'status', 'country_id'];

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id');
    }
}
