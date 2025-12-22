<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Announcement extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title',
        'message',
        'status',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the roles that this announcement is targeted to
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'announcement_role')
            ->withTimestamps();
    }

    /**
     * Scope to get only active announcements
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}


