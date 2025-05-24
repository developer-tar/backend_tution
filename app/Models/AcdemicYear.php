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
    protected $hidden = ['start_year', 'end_year', 'deleted_at', 'created_at', 'updated_at'];

    protected $appends = ['start_end_year'];
    public function getStartEndYearAttribute()
    {

        return $this->start_year . '/' . $this->end_year;
    }
   
}
