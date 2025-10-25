<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseResultUser extends Model
{
    protected $fillable = [
        'student_id',
        'test_id',
        'test_score',
        'attempt_count'
    ];

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function test()
    {
        return $this->belongsTo(CourseTest::class, 'test_id');
    }
}
