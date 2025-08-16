<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoursePrice extends Model
{
    protected $fillable = [
        'course_id',
        'billing_period_id',
        'stripe_product_id',
        'mode_id',
        'stripe_price_id',
        'currency',
        'amount',
    ];
    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id', 'id');
    }
    public function billingPeriod()
    {
        return $this->belongsTo(BillingPeriod::class, 'billing_period_id', 'id');
    }
    public function mode()
    {
        return $this->belongsTo(Mode::class, 'mode_id', 'id');
    }
}
