<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MockExamAnswer extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'mock_exam_option_id',
        'status',
    ];

    public function option()
    {
        return $this->belongsTo(MockExamOption::class, 'mock_exam_option_id');
    }
}
