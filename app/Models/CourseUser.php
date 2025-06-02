<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseUser extends Model
{
    protected $table = 'course_user';
    protected $fillable = [
        'course_id',
        'buyer_id',
        'stripe_session_id',
        'transaction_id',
        'amount',
        'currency',
        'status',
        'paid_at',
        'is_completed',
        'completed_at',
    ];
    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id');
    }
    public function assignment(){
        return $this->hasMany(AssignmentUser::class, 'course_user_id');
    }
}
