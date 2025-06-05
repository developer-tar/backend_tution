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

    public function course(){
        return $this->belongsTo(Course::class, 'course_id','id');
    }
    public function parent(){
        return $this->belongsTo(ManageStudentRecord::class,'parent_id', 'id');
    }
    public function children() {
    return $this->hasMany(ManageStudentRecord::class, 'parent_id', 'id');
}
}
