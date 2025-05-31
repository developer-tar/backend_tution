<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseQuestion extends Model {
    protected $fillable =  [
        'course_test_id',
        'name',
        'duration_in_sec'
    ];
    public function options() {
        return $this->hasMany(CourseOption::class, 'course_question_id', 'id');
    }
}
