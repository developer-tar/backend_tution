<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MockExamQuestion extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'mock_exam_id',
        'question_text',
        'marks',
        'duration_in_sec',
        'order',
        'status',
    ];

    public function mockExam()
    {
        return $this->belongsTo(MockExam::class, 'mock_exam_id');
    }

    public function options()
    {
        return $this->hasMany(MockExamOption::class, 'mock_exam_question_id');
    }

    public function userAnswers()
    {
        return $this->hasMany(MockExamUserAnswer::class, 'mock_exam_question_id');
    }
}
