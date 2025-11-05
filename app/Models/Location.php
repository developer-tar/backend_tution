<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use PDO;

class Location extends Model {
    use SoftDeletes;
    protected $fillable = [
        "name",
        "status",
    ];

    public function courses() {
        return $this->belongsToMany(Course::class, 'course_location', 'location_id', 'course_id');
    }

    public static function getByCourseId($courseId) {
        return self::with(['courses' => function ($q) use ($courseId) {
            $q->where('courses.id', $courseId)->select('courses.id');
        }])
            ->whereHas('courses', function ($q) use ($courseId) {
                $q->where('courses.id', $courseId);
            })
            ->select('locations.id', 'locations.name')
            ->get()
            ->transform(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                ];
            });
    }
}
