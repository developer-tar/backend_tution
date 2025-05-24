<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Media extends Model
{
    protected $fillable = [
        'path',
        'type',
        'model_id',
        'model_type',
    ];
    public function model()
    {
        return $this->morphTo();
    }
}
