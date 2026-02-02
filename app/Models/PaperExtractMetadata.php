<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaperExtractMetadata extends Model
{
    public $timestamps = true;

    protected $fillable = [
        'paper_extract_id',
        'key',
        'value',
        'data_type',
    ];

    protected $casts = [
        'paper_extract_id' => 'integer',
    ];

    /**
     * Get the paper extract this metadata belongs to
     */
    public function paperExtract()
    {
        return $this->belongsTo(PaperExtract::class, 'paper_extract_id');
    }

    /**
     * Get value casted to appropriate type
     */
    public function getCastedValueAttribute()
    {
        return match ($this->data_type) {
            'integer' => (int) $this->value,
            'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($this->value, true),
            default => $this->value,
        };
    }
}
