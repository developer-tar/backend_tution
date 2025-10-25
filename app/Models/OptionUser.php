<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OptionUser extends Model
{
    protected $table = 'option_user';
    
    protected $fillable = [
        'question_user_id',
        'completed_at'
    ];

    protected $casts = [
        'completed_at' => 'datetime'
    ];

    public function questionUser()
    {
        return $this->belongsTo(QuestionUser::class, 'question_user_id');
    }

    public function answerUser()
    {
        return $this->hasOne(AnswerUser::class, 'option_user_id');
    }
}
