<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseOption extends Model
{
    protected $fillable =  [
        'course_question_id',
        'name'
    ];
}
