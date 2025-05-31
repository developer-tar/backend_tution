<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseTest extends Model
{
    protected $fillable = [
        'course_topic_id',
        'course_sub_topic_id',
        'name'
    ];
    public function courseTopic()
    {
        return $this->belongsTo(CourseTopic::class, 'course_topic_id', 'id');
    }
    public function courseSubTopic()
    {
        return $this->belongsTo(CourseSubTopic::class, 'course_sub_topic_id', 'id');
    }
    public function question()
    {
        return $this->hasMany(CourseQuestion::class, 'course_test_id', 'id');
    }   
}
