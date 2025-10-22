<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StudentDetail extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'parent_id',
        'year_id',
        'month_id',
        'day_id',
        'region_id',
        'gender_id',
        'target_school_id',
        'display_name',
        'show_answer_after_n_attempts',
        'allow_view_examiner_report_for_mocks',
        'can_change_password',
        'bio',
        'child_id',
    ];
    protected $casts = [
        'show_answer_after_n_attempts' => 'integer',
        'allow_view_examiner_report_for_mocks' => 'boolean',
        'can_change_password' => 'boolean',
    ];

    /**
     * Get the student user details
     */
    public function student()
    {
        return $this->belongsTo(User::class, 'child_id');
    }

    /**
     * Get the parent user details
     */
    public function parent()
    {
        return $this->belongsTo(User::class, 'parent_id');
    }

    /**
     * Get the year details
     */
    public function year()
    {
        return $this->belongsTo(Year::class, 'year_id');
    }

    /**
     * Get the month details
     */
    public function month()
    {
        return $this->belongsTo(Month::class, 'month_id');
    }

    /**
     * Get the day details
     */
    public function day()
    {
        return $this->belongsTo(Day::class, 'day_id');
    }

    /**
     * Get the region details
     */
    public function region()
    {
        return $this->belongsTo(Region::class, 'region_id');
    }

    /**
     * Get the gender details
     */
    public function gender()
    {
        return $this->belongsTo(Gender::class, 'gender_id');
    }

    /**
     * Get the target school details
     */
    public function targetSchool()
    {
        return $this->belongsTo(TargetSchool::class, 'target_school_id');
    }
}
