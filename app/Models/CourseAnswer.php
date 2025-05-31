<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseAnswer extends Model
{
    protected $fillable =  [
        'course_option_id',
    ];
    public function courseOption()
    {
        return $this->belongsTo(CourseOption::class, 'course_option_id', 'id');
    }
}
