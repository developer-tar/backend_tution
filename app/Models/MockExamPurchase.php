<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MockExamPurchase extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'student_id',
        'mock_exam_id',
        'stripe_session_id',
        'transaction_id',
        'amount',
        'currency',
        'purchased_at',
        'started_at',
        'completed_at',
        'score',
        'total_marks',
        'status',
        'purchased_by',
    ];

    protected $casts = [
        'purchased_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function mockExam()
    {
        return $this->belongsTo(MockExam::class, 'mock_exam_id');
    }

    public function userAnswers()
    {
        return $this->hasMany(MockExamUserAnswer::class, 'mock_exam_purchase_id');
    }
}
