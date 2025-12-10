<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaperPurchase extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'student_id',
        'paper_id',
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
        'payment_status',
    ];

    protected $casts = [
        'purchased_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'amount' => 'decimal:2',
        'score' => 'decimal:2',
        'total_marks' => 'integer',
        'status' => 'integer',
        'user_id' => 'integer',
        'student_id' => 'integer',
        'paper_id' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function paper()
    {
        return $this->belongsTo(Paper::class, 'paper_id');
    }

    public function userAnswers()
    {
        return $this->hasMany(PaperUserAnswer::class, 'paper_purchase_id');
    }
}







