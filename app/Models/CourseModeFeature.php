<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseModeFeature extends Model
{
    protected $fillable = [
        'online_features_names',
        'in_person_features_names',
        'course_id',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }
}
