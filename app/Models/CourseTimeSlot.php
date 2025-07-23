<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseTimeSlot extends Model
{
    protected $fillable =[
        'course_id',
        'academic_course_id',
        'weekday_id',
        'location_id',
        'start_time',
        'end_time',
        'status',
        'seats',
    ];
    public function locations(){
        return $this->belongsTo(Course::class,'location_id', 'id');
    }
    public function courses(){
        return $this->belongsTo(Course::class,'course_id', 'id');
    }
}
