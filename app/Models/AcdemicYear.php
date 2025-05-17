<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AcdemicYear extends Model
{
    use SoftDeletes;
    protected $fillable = [
        "start_year",
        "end_year",
    ];
}
