<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
class MockExam extends Model implements HasMedia
{
    use SoftDeletes, InteractsWithMedia;

    protected $fillable = [
        'name',
        'description',
        'category_id',
        'format_id',
        'price',
        'currency',
        'duration_minutes',
        'total_marks',
        'school_id',
        'status',
        'stripe_product_id',
        'stripe_price_id',
        'slug',
    ];

    public function category()
    {
        return $this->belongsTo(MockExamCategory::class, 'category_id');
    }

    public function school()
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    public function questions()
    {
        return $this->hasMany(MockExamQuestion::class, 'mock_exam_id');
    }

    public function purchases()
    {
        return $this->hasMany(MockExamPurchase::class, 'mock_exam_id');
    }
    public function format()
    {
        return $this->belongsTo(Format::class, 'format_id');
    }
}
