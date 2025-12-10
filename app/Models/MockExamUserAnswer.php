<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MockExamUserAnswer extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'mock_exam_purchase_id',
        'mock_exam_question_id',
        'mock_exam_option_id',
        'is_correct',
    ];

    protected $casts = [
        'is_correct' => 'boolean',
        'mock_exam_purchase_id' => 'integer',
        'mock_exam_question_id' => 'integer',
        'mock_exam_option_id' => 'integer',
    ];

    public function purchase()
    {
        return $this->belongsTo(MockExamPurchase::class, 'mock_exam_purchase_id');
    }

    public function question()
    {
        return $this->belongsTo(MockExamQuestion::class, 'mock_exam_question_id');
    }

    public function option()
    {
        return $this->belongsTo(MockExamOption::class, 'mock_exam_option_id');
    }
}
