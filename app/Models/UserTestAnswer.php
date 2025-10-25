<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserTestAnswer extends Model
{
    protected $fillable = [
        'user_id',
        'test_id',
        'question_id',
        'selected_option_id',
        'time_taken_seconds',
        'is_correct',
        'attempt_number'
    ];

    protected $casts = [
        'is_correct' => 'boolean'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function test()
    {
        return $this->belongsTo(CourseTest::class, 'test_id');
    }

    public function question()
    {
        return $this->belongsTo(CourseQuestion::class, 'question_id');
    }

    public function selectedOption()
    {
        return $this->belongsTo(CourseOption::class, 'selected_option_id');
    }
}
