<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CourseInstallmentPayment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'course_id',
        'course_installment_id',
        'course_installment_item_id',
        'user_id',
        'student_id',
        'stripe_session_id',
        'stripe_payment_intent_id',
        'transaction_id',
        'installment_number',
        'amount',
        'currency',
        'status',
        'due_date',
        'paid_at',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'due_date' => 'date',
        'paid_at' => 'datetime',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    public function courseInstallment()
    {
        return $this->belongsTo(CourseInstallment::class, 'course_installment_id');
    }

    public function courseInstallmentItem()
    {
        return $this->belongsTo(CourseInstallmentItem::class, 'course_installment_item_id');
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
