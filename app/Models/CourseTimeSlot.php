<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;

class CourseTimeSlot extends Model {
    use SoftDeletes;
    protected $fillable = [
        'course_id',
        'academic_course_id',
        'weekday_id',
        'location_id',
        'start_time',
        'end_time',
        'status',
        'seats',
    ];
    public static function storeTimeSlot(array $data): array {
        $timeSlot = self::firstOrCreate([
            'course_id'           => $data['course_id'],
            'academic_course_id'  => $data['academic_course_id'],
            'weekday_id'          => $data['weekday_id'],
            'location_id'         => $data['location_id'],
            'start_time'          => $data['start_time'],
            'end_time'            => $data['end_time'],
        ], [
            'seats'               => $data['seats'],
        ]);

        if ($timeSlot->wasRecentlyCreated) {
            return [
                'success' => true,
                'timeslot' => $timeSlot,
                'message' => 'New Timeslot created successfully.'
            ];
        }

        return [
            'success' => false,
            'timeslot' => $timeSlot,
            'message' => 'This timeslot already exists.'
        ];
    }
    public function deleteSlot(): bool {
        return $this->delete();
    }
    public static function getTimeSlots($aCId, $locId, $wId) {
        return self::select('id', 'start_time', 'end_time')
            ->where([
                'academic_course_id' => $aCId,
                'location_id'        => $locId,
                'weekday_id'         => $wId,
            ])
            ->orderBy('end_time')
            ->get();
    }
    public static function fetchFiltered(Request $request) {
        $acId = $request->input('academic_course_id');
        $locId = $request->input('location_id');
        $weekDayId = $request->input('weekday_id');

        return self::with(['locations:id,name', 'courses:id,name', 'weekDays:id,name'])
            ->select('id', 'start_time', 'end_time', 'location_id', 'course_id', 'weekday_id', 'seats')
            ->when($acId, fn($q) => $q->orWhere('academic_course_id', $acId))
            ->when($locId, fn($q) => $q->orWhere('location_id', $locId))
            ->when($weekDayId, fn($q) => $q->orWhere('weekday_id', $weekDayId))
            ->orderBy('end_time')
            ->get()
            ->transform(function ($item) {
                return [
                    "id"            => $item->id,
                    "course_name"   => optional($item->courses)->name,
                    "location_name" => optional($item->locations)->name,
                    "start_time"    => $item->start_time,
                    "end_time"      => $item->end_time,
                    "week_days"     => optional($item->weekDays)->name,
                    "seats"         => $item->seats,
                ];
            });
    }
    public function locations() {
        return $this->belongsTo(Location::class, 'location_id', 'id');
    }
    public function courses() {
        return $this->belongsTo(Course::class, 'course_id', 'id');
    }
    public function weekDays() {
        return $this->belongsTo(WeekDay::class, 'weekday_id', 'id');
    }
}
