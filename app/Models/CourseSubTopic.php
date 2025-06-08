<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class CourseSubTopic extends Model implements HasMedia {
    use InteractsWithMedia;
    protected $fillable = [
        'course_topic_id',
        'name',
    ];
    public function courseTopic() {
        return $this->belongsTo(CourseTopic::class, 'course_topic_id', 'id');
    }
    public function manageStudentRecord() {
        return $this->morphMany(ManageStudentRecord::class, 'model');
    }
    public function test(){
        return $this->hasMany(CourseTest::class, 'course_sub_topic_id', 'id');
    }
}
