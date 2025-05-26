<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseTest extends Model {
    protected $fillable = [
        'course_topic_id',
        'course_sub_topic_id',
        'name'
    ];
}
