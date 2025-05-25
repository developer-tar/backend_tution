<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
class CourseTopic extends Model implements HasMedia
{
     use InteractsWithMedia;
     protected $fillable = [
        'course_assignment_id',
        'subject_id',
        'name'
     ];
     public function subtopic(){
      return $this->hasMany(CourseSubTopic::class,'course_topic_id', 'id');
     }
}
