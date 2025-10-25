<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnswerUser extends Model
{
    protected $table = 'answer_user';
    
    protected $fillable = [
        'option_user_id',
        'taken_time_in_sec',
        'is_completed',
        'completed_at'
    ];

    protected $casts = [
        'completed_at' => 'datetime'
    ];

    public function optionUser()
    {
        return $this->belongsTo(OptionUser::class, 'option_user_id');
    }
}
