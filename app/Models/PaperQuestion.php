<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaperQuestion extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'paper_id',
        'question_text',
        'marks',
        'duration_in_sec',
        'order',
        'status',
    ];

    protected $casts = [
        'paper_id' => 'integer',
        'marks' => 'integer',
        'duration_in_sec' => 'integer',
        'order' => 'integer',
        'status' => 'integer',
    ];

    public function paper()
    {
        return $this->belongsTo(Paper::class, 'paper_id');
    }

    public function options()
    {
        return $this->hasMany(PaperOption::class, 'paper_question_id');
    }

    public function userAnswers()
    {
        return $this->hasMany(PaperUserAnswer::class, 'paper_question_id');
    }
}







