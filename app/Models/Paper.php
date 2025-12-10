<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Paper extends Model implements HasMedia
{
    use SoftDeletes, InteractsWithMedia;

    protected $fillable = [
        'name',
        'description',
        'category_id',
        'format_id',
        'price',
        'currency',
        'duration_minutes',
        'total_marks',
        'school_id',
        'status',
        'stripe_product_id',
        'stripe_price_id',
        'slug',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'duration_minutes' => 'integer',
        'total_marks' => 'integer',
        'status' => 'integer',
        'category_id' => 'integer',
        'format_id' => 'integer',
        'school_id' => 'integer',
    ];

    public function category()
    {
        return $this->belongsTo(MockExamCategory::class, 'category_id');
    }

    public function school()
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    public function questions()
    {
        return $this->hasMany(PaperQuestion::class, 'paper_id');
    }

    public function purchases()
    {
        return $this->hasMany(PaperPurchase::class, 'paper_id');
    }

    public function format()
    {
        return $this->belongsTo(Format::class, 'format_id');
    }

    public function registerMediaCollections(): void
    {
        // Existing paper_image collection
        $this->addMediaCollection('paper_image')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp']);
        
        // New collection for multiple PDFs (max 10)
        $this->addMediaCollection('paper_pdfs')
            ->acceptsMimeTypes(['application/pdf']);
    }
}







