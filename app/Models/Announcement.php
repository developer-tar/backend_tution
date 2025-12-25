<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title',
        'message',
        'status',            //[draft => 0 ,publish => 1]
        'module_id',
        'academic_year_id',
        'course_id',
        'class_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the roles that this announcement is targeted to
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'announcement_role')
            ->withTimestamps();
    }

    /**
     * Get the module for this announcement
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class, 'module_id');
    }

    /**
     * Get the academic year for this announcement
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcdemicYear::class, 'academic_year_id');
    }

    /**
     * Get the course for this announcement
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    /**
     * Get the class (timeslot) for this announcement
     */
    public function class(): BelongsTo
    {
        return $this->belongsTo(CourseTimeSlot::class, 'class_id');
    }

    /**
     * Scope to get only published announcements
     * Status: publish = 1 (config('constants.announcement_status.publish'))
     */
    public function scopePublished($query)
    {
        return $query->where('status', config('constants.announcement_status.publish'));
    }

    /**
     * Scope to get only draft announcements
     * Status: draft = 0 (config('constants.announcement_status.draft'))
     */
    public function scopeDraft($query)
    {
        return $query->where('status', config('constants.announcement_status.draft'));
    }
}
