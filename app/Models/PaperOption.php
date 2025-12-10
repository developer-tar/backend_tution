<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaperOption extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'paper_question_id',
        'option_text',
        'order',
        'status',
    ];

    protected $casts = [
        'paper_question_id' => 'integer',
        'order' => 'integer',
        'status' => 'integer',
    ];

    public function question()
    {
        return $this->belongsTo(PaperQuestion::class, 'paper_question_id');
    }

    public function answer()
    {
        return $this->hasOne(PaperAnswer::class, 'paper_option_id');
    }

    public function userAnswers()
    {
        return $this->hasMany(PaperUserAnswer::class, 'paper_option_id');
    }
}







