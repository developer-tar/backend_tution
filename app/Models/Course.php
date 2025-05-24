<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    protected $fillable = [
        'created_id',
        'name',
        'type_of_course',
        'amount',
        'status',
        'product_id',
        'price_id',
        'description'
    ];
    public function subjects()
    {
        return $this->belongsToMany(Subject::class, 'course_subject', 'course_id', 'subject_id');
    }
    public function locations()
    {
        return $this->belongsToMany(Location::class, 'course_location', 'course_id', 'location_id');
    }
    public function features()
    {
        return $this->hasMany(Feature::class, 'course_id', 'id');
    }
    public function acdemicyears()
    {
        return $this->belongsToMany(AcdemicYear::class, 'acdemic_course', 'course_id', 'acdemic_id');
    }
    public function media()
    {
        return $this->morphMany(Media::class, 'model');
    }

}
