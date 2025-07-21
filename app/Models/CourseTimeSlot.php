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
}
