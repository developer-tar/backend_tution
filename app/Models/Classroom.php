<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Classroom extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_ONGOING = 'ongoing';
    public const STATUS_ENDED = 'ended';

    protected $fillable = [
        'course_id',
        'user_id',
        'name',
        'description',
        'capacity',
        'schedule_summary',
        'room_code',
        'drawing_enabled',
        'start_time',
        'end_time',
    ];

    protected function casts(): array
    {
        return [
            'drawing_enabled' => 'boolean',
            'start_time' => 'datetime',
            'end_time' => 'datetime',
        ];
    }

    /**
     * Get status based on current time: pending, ongoing, or ended.
     */
    public function getStatusAttribute(): string
    {
        $now = Carbon::now();
        if (!$this->start_time || !$this->end_time) {
            return self::STATUS_PENDING;
        }
        if ($now->lt($this->start_time)) {
            return self::STATUS_PENDING;
        }
        if ($now->lte($this->end_time)) {
            return self::STATUS_ONGOING;
        }
        return self::STATUS_ENDED;
    }

    protected static function booted(): void
    {
        static::creating(function (Classroom $classroom) {
            if (empty($classroom->room_code)) {
                $classroom->room_code = static::generateUniqueRoomCode();
            }
        });
    }

    public static function generateUniqueRoomCode(): string
    {
        do {
            $code = Str::random(12);
        } while (static::where('room_code', $code)->exists());

        return $code;
    }

    /**
     * The course this classroom belongs to.
     */
    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * The tutor who teaches this class.
     */
    public function tutor()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
