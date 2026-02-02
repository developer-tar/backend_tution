<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

use App\Models\User;

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

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [];

    /**
     * Get the attributes that should be included when the model is serialized.
     *
     * @return array
     */
    public function toArray()
    {
        return parent::toArray();
    }

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

    /**
     * Get the user who created this extract
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
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
     * Scope to search in name, description, and subject
     */
    public function scopeSearch($query, $searchTerm)
    {
        return $query->where(function ($q) use ($searchTerm) {
            $q->where('name', 'like', "%{$searchTerm}%")
                ->orWhere('description', 'like', "%{$searchTerm}%")
                ->orWhere('subject', 'like', "%{$searchTerm}%");
        });
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
