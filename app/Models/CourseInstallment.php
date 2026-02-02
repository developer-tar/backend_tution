<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CourseInstallment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'course_id',
        'course_price_id',
        'number_of_installments',
        'total_course_fee',
        'currency',
        'is_active',
    ];

    protected $casts = [
        'total_course_fee' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    public function coursePrice()
    {
        return $this->belongsTo(CoursePrice::class, 'course_price_id');
    }

    public function installmentItems()
    {
        return $this->hasMany(CourseInstallmentItem::class, 'course_installment_id');
    }

    public function installmentPayments()
    {
        return $this->hasMany(CourseInstallmentPayment::class, 'course_installment_id');
    }
}
