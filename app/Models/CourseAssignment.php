<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseAssignment extends Model
{
    protected $fillable = [
        'week_id',
        'acdemic_course_id'
    ];
    
    public  function weeks(){
        return $this->belongsTo(Week::class, 'week_id','id');
    }
}
