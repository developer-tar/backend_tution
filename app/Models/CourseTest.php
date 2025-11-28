<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CourseTest extends Model {
    use SoftDeletes;
    protected $fillable = [
        'course_topic_id',
        'course_sub_topic_id',
        'name'
    ];
    public function courseTopic() {
        return $this->belongsTo(CourseTopic::class, 'course_topic_id', 'id');
    }
    public function courseSubTopic() {
        return $this->belongsTo(CourseSubTopic::class, 'course_sub_topic_id', 'id');
    }
    public function question() {
        return $this->hasMany(CourseQuestion::class, 'course_test_id', 'id');
    }
    public function manageStudentRecord() {
        return $this->morphMany(ManageStudentRecord::class, 'model');
    }
}
