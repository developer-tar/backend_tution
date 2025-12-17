<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Announcement extends Model implements HasMedia
{
    use SoftDeletes, InteractsWithMedia;

    protected $fillable = [
        'title',
        'description',
        'content',
        'type',
        'status',
        'published_at',
        'expires_at',
        'created_by',
        'target_roles',
        'target_years',
        'is_pinned',
    ];

    protected $casts = [
        'status' => 'integer',
        'published_at' => 'datetime',
        'expires_at' => 'datetime',
        'target_roles' => 'array',
        'target_years' => 'array',
        'is_pinned' => 'boolean',
        'created_by' => 'integer',
    ];

    /**
     * Get the user who created the announcement
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope to get only published announcements
     */
    public function scopePublished($query)
    {
        return $query->where('status', config('constants.statuses.APPROVED'))
            ->where(function ($q) {
                $q->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', now());
            });
    }

    /**
     * Scope to get pinned announcements
     */
    public function scopePinned($query)
    {
        return $query->where('is_pinned', true);
    }

    /**
     * Scope to filter by type
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to filter by target roles
     */
    public function scopeForRoles($query, array $roles)
    {
        return $query->where(function ($q) use ($roles) {
            $q->whereNull('target_roles');
            foreach ($roles as $role) {
                $q->orWhereJsonContains('target_roles', $role);
            }
        });
    }

    /**
     * Scope to filter by target years (grades)
     */
    public function scopeForYears($query, array $yearIds)
    {
        return $query->where(function ($q) use ($yearIds) {
            $q->whereNull('target_years');
            foreach ($yearIds as $yearId) {
                $q->orWhereJsonContains('target_years', (int)$yearId);
            }
        });
    }
}
