<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaperExtractQuestionMedia extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'paper_extract_question_id',
        'media_type',
        'file_path',
        'file_name',
        'mime_type',
        'width',
        'height',
        'page_number',
        'position',
        'description',
    ];

    protected $casts = [
        'width' => 'integer',
        'height' => 'integer',
        'page_number' => 'integer',
        'position' => 'integer',
    ];

    /**
     * Get the question this media belongs to
     */
    public function question()
    {
        return $this->belongsTo(PaperExtractQuestion::class, 'paper_extract_question_id');
    }
}
