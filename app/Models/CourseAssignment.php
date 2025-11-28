<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CourseAssignment extends Model
{
    use SoftDeletes;
    
    protected $fillable = [
        'week_id',
        'acdemic_course_id',
        'status'
    ];

    public function weeks()
    {
        return $this->belongsTo(Week::class, 'week_id', 'id');
    }
    public function acdemicCourses()
    {
        return $this->belongsTo(AcdemicCourse::class, 'acdemic_course_id', 'id');
    }
    public function manageStudentRecord()
    {
        return $this->morphMany(ManageStudentRecord::class, 'model');
    }
    
    public function topics()
    {
        return $this->hasMany(CourseTopic::class, 'course_assignment_id', 'id');
    }
}
