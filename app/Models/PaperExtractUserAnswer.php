<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaperExtractUserAnswer extends Model
{
    protected $fillable = [
        'paper_purchase_id',
        'paper_extract_question_id',
        'selected_option_key',
        'answer_text',
        'is_correct',
    ];

    protected $casts = [
        'is_correct' => 'boolean',
        'paper_purchase_id' => 'integer',
        'paper_extract_question_id' => 'integer',
    ];

    public function purchase()
    {
        return $this->belongsTo(PaperPurchase::class, 'paper_purchase_id');
    }

    public function question()
    {
        return $this->belongsTo(PaperExtractQuestion::class, 'paper_extract_question_id');
    }
}
