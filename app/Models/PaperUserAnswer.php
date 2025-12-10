<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaperUserAnswer extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'paper_purchase_id',
        'paper_question_id',
        'paper_option_id',
        'is_correct',
    ];

    protected $casts = [
        'is_correct' => 'boolean',
        'paper_purchase_id' => 'integer',
        'paper_question_id' => 'integer',
        'paper_option_id' => 'integer',
    ];

    public function purchase()
    {
        return $this->belongsTo(PaperPurchase::class, 'paper_purchase_id');
    }

    public function question()
    {
        return $this->belongsTo(PaperQuestion::class, 'paper_question_id');
    }

    public function option()
    {
        return $this->belongsTo(PaperOption::class, 'paper_option_id');
    }
}







