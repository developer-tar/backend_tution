<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MockExamOption extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'mock_exam_question_id',
        'option_text',
        'order',
        'status',
    ];

    public function question()
    {
        return $this->belongsTo(MockExamQuestion::class, 'mock_exam_question_id');
    }

    public function answer()
    {
        return $this->hasOne(MockExamAnswer::class, 'mock_exam_option_id');
    }

    public function userAnswers()
    {
        return $this->hasMany(MockExamUserAnswer::class, 'mock_exam_option_id');
    }
}
