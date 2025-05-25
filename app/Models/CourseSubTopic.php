<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
class CourseSubTopic extends Model implements HasMedia
{
     use InteractsWithMedia;
     protected $fillable = [
        'course_topic_id',
        'name',
     ];
}
