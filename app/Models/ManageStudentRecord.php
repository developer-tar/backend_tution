<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ManageStudentRecord extends Model {
    
    protected $fillable = [
        'parent_id',
        'course_id',
        'buyer_id',
        'model_type',
        'model_id',
        'stripe_session_id',
        'transaction_id',
        'amount',
        'currency',
        'status',
        'paid_at',
        'is_completed',
        'completed_at',
    ];
}
