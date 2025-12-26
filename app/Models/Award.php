<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Award extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'type',
        'criteria',
        'certificate_template',
        'status',
    ];

    protected $casts = [
        'status' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get all certificates for this award
     */
    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class, 'award_id');
    }

    /**
     * Scope to get only active awards
     */
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }
}
