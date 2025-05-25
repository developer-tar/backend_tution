<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcdemicCourse extends Model
{
    
    public $table = 'acdemic_course';
    public function courses(){
        return $this->belongsTo(Course::class,'course_id', 'id');
    }
}
