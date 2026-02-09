<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Course extends Model implements HasMedia
{
    use InteractsWithMedia;
    protected $fillable = [
        'created_id',
        'name',
        'type_of_course',
        'amount',
        'status',
        'product_id',
        'price_id',
        'description',
        'slug',
    ];
    public function subjects()
    {
        return $this->belongsToMany(Subject::class, 'course_subject', 'course_id', 'subject_id')->wherePivotNull('deleted_at');
    }

    public function tutors()
    {
        return $this->belongsToMany(User::class, 'course_tutor', 'course_id', 'user_id');
    }

    public function classrooms()
    {
        return $this->hasMany(Classroom::class);
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
    public function modes()
    {
        return $this->belongsToMany(Mode::class, 'mode_user', 'course_id', 'mode_id');
    }
    public function acdemiccourse()
    {
        return $this->hasMany(AcdemicCourse::class, 'course_id', 'id');
    }
    public function manageStudentRecord()
    {
        return $this->morphMany(ManageStudentRecord::class, 'model');
    }
    public function prices()
    {
        return $this->hasMany(CoursePrice::class, 'course_id', 'id');
    }
    public function slots()
    {
        return $this->hasMany(CourseTimeSlot::class, 'course_id', 'id');
    }
    public function modefeatures()
    {
        return $this->hasMany(CourseModeFeature::class, 'course_id', 'id');
    }
    public function installments()
    {
        return $this->hasMany(CourseInstallment::class, 'course_id', 'id');
    }
    public function registrationPayments()
    {
        return $this->hasMany(CourseRegistrationPayment::class, 'course_id', 'id');
    }
    public function installmentPayments()
    {
        return $this->hasMany(CourseInstallmentPayment::class, 'course_id', 'id');
    }
}
