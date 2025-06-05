<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcdemicCourse extends Model
{
    
    public $table = 'acdemic_course';
    public function courses(){
        return $this->belongsTo(Course::class,'course_id', 'id');
    }
    public function acdemicyears(){
        return $this->belongsTo(AcdemicYear::class,'acdemic_id', 'id');
    }
    public function assignment(){
        return $this->hasMany(CourseAssignment::class, 'acdemic_course_id', 'id');
    }
}
