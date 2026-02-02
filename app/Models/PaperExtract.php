<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaperExtract extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'paper_id',
        'title',
        'name',
        'description',
        'category_id',
        'format_id',
        'price',
        'currency',
        'slug',
        'source_file_path',
        'extracted_file_path',
        'extraction_status',
        'extraction_error',
        'metadata',
        'total_questions',
        'total_pages',
        'subject',
        'exam_board',
        'year',
        'level',
        'status',
        'created_by',
    ];

    protected $casts = [
        'metadata' => 'array',
        'total_questions' => 'integer',
        'total_pages' => 'integer',
        'status' => 'integer',
        'paper_id' => 'integer',
        'category_id' => 'integer',
        'format_id' => 'integer',
        'price' => 'decimal:2',
        'created_by' => 'integer',
    ];

    /**
     * Get the paper that this extract belongs to
     */
    public function paper()
    {
        return $this->belongsTo(Paper::class, 'paper_id');
    }

    /**
     * Get the category
     */
    public function category()
    {
        return $this->belongsTo(\App\Models\MockExamCategory::class, 'category_id');
    }

    /**
     * Get the format
     */
    public function format()
    {
        return $this->belongsTo(\App\Models\Format::class, 'format_id');
    }

    /**
     * Get the user who created this extract
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get all questions extracted from this paper
     */
    public function questions()
    {
        return $this->hasMany(PaperExtractQuestion::class, 'paper_extract_id');
    }

    /**
     * Get all metadata for this extract
     */
    public function extractMetadata()
    {
        return $this->hasMany(PaperExtractMetadata::class, 'paper_extract_id');
    }

    /**
     * Scope to filter by extraction status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('extraction_status', $status);
    }

    /**
     * Scope to filter by extraction status (alias for byStatus)
     */
    public function scopeByExtractionStatus($query, $status)
    {
        return $query->where('extraction_status', $status);
    }

    /**
     * Scope to filter by subject
     */
    public function scopeBySubject($query, $subject)
    {
        return $query->where('subject', $subject);
    }

    /**
     * Scope to filter by year
     */
    public function scopeByYear($query, $year)
    {
        return $query->where('year', $year);
    }

    /**
     * Scope to filter by level
     */
    public function scopeByLevel($query, $level)
    {
        return $query->where('level', $level);
    }

    /**
     * Scope to search in title and description
     */
    public function scopeSearch($query, $searchTerm)
    {
        return $query->where(function ($q) use ($searchTerm) {
            $q->where('title', 'like', "%{$searchTerm}%")
              ->orWhere('description', 'like', "%{$searchTerm}%")
              ->orWhere('subject', 'like', "%{$searchTerm}%");
        });
    }
}

