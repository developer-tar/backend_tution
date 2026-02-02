<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CourseRegistrationPayment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'course_id',
        'course_price_id',
        'user_id',
        'student_id',
        'stripe_session_id',
        'stripe_payment_intent_id',
        'transaction_id',
        'amount',
        'currency',
        'status',
        'paid_at',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    public function coursePrice()
    {
        return $this->belongsTo(CoursePrice::class, 'course_price_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
