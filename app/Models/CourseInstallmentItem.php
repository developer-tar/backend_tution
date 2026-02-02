<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CourseInstallmentItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'course_installment_id',
        'installment_number',
        'amount',
        'due_date',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'due_date' => 'date',
    ];

    public function courseInstallment()
    {
        return $this->belongsTo(CourseInstallment::class, 'course_installment_id');
    }

    public function installmentPayments()
    {
        return $this->hasMany(CourseInstallmentPayment::class, 'course_installment_item_id');
    }
}
