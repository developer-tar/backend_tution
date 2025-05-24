<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Week extends Model
{
    protected $fillable = [
        'academic_year_id',
        'week_number',
        'start_date',
        'end_date', 
    ];
}
