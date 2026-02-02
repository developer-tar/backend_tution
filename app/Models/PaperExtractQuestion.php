<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaperExtractQuestion extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'paper_extract_id',
        'question_number',
        'section',
        'question_text',
        'question_text_clean',
        'question_type',
        'marks',
        'page_number',
        'options',
        'correct_answer',
        'answer_explanation',
        'topic',
        'subtopic',
        'keywords',
        'metadata',
        'order',
        'status',
    ];

    protected $casts = [
        'options' => 'array',
        'keywords' => 'array',
        'metadata' => 'array',
        'question_number' => 'integer',
        'marks' => 'integer',
        'page_number' => 'integer',
        'order' => 'integer',
        'status' => 'integer',
    ];

    /**
     * Get the paper extract this question belongs to
     */
    public function paperExtract()
    {
        return $this->belongsTo(PaperExtract::class, 'paper_extract_id');
    }

    /**
     * Get the media (images/charts) associated with this question
     */
    public function media()
    {
        return $this->hasMany(PaperExtractQuestionMedia::class, 'paper_extract_question_id');
    }

    /**
     * Scope to filter by question type
     */
    public function scopeByType($query, $type)
    {
        return $query->where('question_type', $type);
    }

    /**
     * Scope to filter by topic
     */
    public function scopeByTopic($query, $topic)
    {
        return $query->where('topic', $topic);
    }

    /**
     * Scope to search in question text
     */
    public function scopeSearch($query, $searchTerm)
    {
        return $query->where(function ($q) use ($searchTerm) {
            $q->where('question_text', 'like', "%{$searchTerm}%")
                ->orWhere('question_text_clean', 'like', "%{$searchTerm}%")
                ->orWhereJsonContains('keywords', $searchTerm);
        });
    }
}
