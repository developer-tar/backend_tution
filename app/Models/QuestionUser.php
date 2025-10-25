<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionUser extends Model
{
    protected $table = 'question_user';
    
    protected $fillable = [
        'test_user_id',
        'is_completed',
        'completed_at'
    ];

    protected $casts = [
        'completed_at' => 'datetime'
    ];

    public function testUser()
    {
        return $this->belongsTo(TestUser::class, 'test_user_id');
    }

    public function optionUsers()
    {
        return $this->hasMany(OptionUser::class, 'question_user_id');
    }
}
