<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CourseTimeSlot extends Model
{
    use SoftDeletes;
    
    /**
     * The table associated with the model.
     * Explicitly set to ensure we're using the course_time_slots table
     *
     * @var string
     */
    protected $table = 'course_time_slots';
    
    protected $fillable = [
        'course_id',
        'academic_course_id',
        'weekday_id',
        'location_id',
        'start_time',
        'end_time',
        'status',
        'seats',
        'class_name',
        'remaining_seats',
    ];
    public static function storeTimeSlot(array $data): array
    {
        $timeSlot = self::firstOrCreate([
            'course_id'           => $data['course_id'],
            'academic_course_id'  => $data['academic_course_id'],
            'weekday_id'          => $data['weekday_id'],
            'location_id'         => $data['location_id'],
            'start_time'          => $data['start_time'],
            'end_time'            => $data['end_time'],
        ], [
            'seats'               => $data['seats'],
            'remaining_seats'     => $data['seats'],
            'class_name'          => $data['class_name'],
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
    public function deleteSlot(): bool
    {
        return $this->delete();
    }
    public static function getTimeSlots($aCId, $locId, $wId)
    {
        return self::select('id', 'start_time', 'end_time')
            ->where([
                'academic_course_id' => $aCId,
                'location_id'        => $locId,
                'weekday_id'         => $wId,
            ])
            ->orderBy('end_time')
            ->get();
    }
    public static function fetchFiltered(Request $request)
    {
        $acId = $request->input('academic_course_id');
        $locId = $request->input('location_id');
        $weekDayId = $request->input('weekday_id');

        return self::with(['locations:id,name', 'courses:id,name', 'weekDays:id,name'])
            ->select('id', 'start_time', 'end_time', 'location_id', 'course_id', 'weekday_id', 'seats', 'class_name')
            ->when($acId, fn($q) => $q->orWhere('academic_course_id', $acId))
            ->when($locId, fn($q) => $q->orWhere('location_id', $locId))
            ->when($weekDayId, fn($q) => $q->orWhere('weekday_id', $weekDayId))
            ->orderBy('end_time')
            ->get()
            ->transform(function ($item) {
                return [
                    "id"            => $item->id,
                    "course_name"   => optional($item->courses)->name,
                    "class_name"    => $item->class_name,
                    "location_name" => optional($item->locations)->name,
                    "start_time"    => $item->start_time,
                    "end_time"      => $item->end_time,
                    "week_days"     => optional($item->weekDays)->name,
                    "seats"         => $item->seats,
                ];
            });
    }
    public static function updateTimeSlot(array $data): array
    {

        $timeSlot = self::find($data['timeslot_id']);

        $timeSlot->update([
            'course_id'           => $data['course_id'],
            'academic_course_id'  => $data['academic_course_id'],
            'weekday_id'          => $data['weekday_id'],
            'location_id'         => $data['location_id'],
            'start_time'          => $data['start_time'],
            'end_time'            => $data['end_time'],
            'seats'               => $data['seats'],
            'class_name'          => $data['class_name'],
            'remaining_seats'     => $data['seats'],
        ]);

        return [
            'success' => true,
            'timeslot' => $timeSlot,
            'message' => 'Timeslot has been updated successfully.'
        ];
    }

    public function locations()
    {
        return $this->belongsTo(Location::class, 'location_id', 'id');
    }
    public function courses()
    {
        return $this->belongsTo(Course::class, 'course_id', 'id');
    }
    public function weekDays()
    {
        return $this->belongsTo(WeekDay::class, 'weekday_id', 'id');
    }
    public function academicCourse()
    {
        return $this->belongsTo(AcdemicCourse::class, 'academic_course_id', 'id');
    }

    /**
     * Get all course time slots for announcements with related data
     * Fetches all active (non-deleted) course time slots from the course_time_slots table
     * 
     * This method explicitly queries the 'course_time_slots' table (defined in $table property)
     * 
     * @return \Illuminate\Support\Collection
     */
    public static function getAllForAnnouncements()
    {
        try {
            \Log::info('Starting getAllForAnnouncements - Querying course_time_slots table', [
                'model_table' => (new self())->getTable()
            ]);
            
            // Fetch all active course time slots from course_time_slots table
            // self:: uses the CourseTimeSlot model which has $table = 'course_time_slots' set
            $slots = self::with(['locations:id,name', 'courses:id,name', 'weekDays:id,name'])
                ->select('id', 'course_id', 'academic_course_id', 'location_id', 'weekday_id', 'start_time', 'end_time', 'class_name', 'status')
                ->whereNull('deleted_at') // Explicitly exclude soft-deleted records
                ->orderBy('course_id')
                ->orderBy('weekday_id')
                ->orderBy('start_time')
                ->get();
            
            \Log::info('Course time slots fetched from course_time_slots table', [
                'table' => (new self())->getTable(),
                'total_count' => $slots->count(),
                'sample_ids' => $slots->take(5)->pluck('id')->toArray()
            ]);

            \Log::info('Course time slots fetched from database', [
                'total_count' => $slots->count(),
                'sample_ids' => $slots->take(5)->pluck('id')->toArray()
            ]);

            if ($slots->isEmpty()) {
                \Log::info('No course time slots found in database');
                return collect([]);
            }

            // Get academic course data in a separate query to avoid relationship issues
            $academicCourseIds = $slots->pluck('academic_course_id')->filter()->unique();
            $academicCourses = [];
            
            if ($academicCourseIds->isNotEmpty()) {
                try {
                    $academicCoursesData = \App\Models\AcdemicCourse::whereIn('id', $academicCourseIds)
                        ->with('acdemicyears:id,start_year,end_year')
                        ->get();

                    foreach ($academicCoursesData as $ac) {
                        $academicYear = 'N/A';
                        if ($ac->acdemicyears) {
                            $academicYear = $ac->acdemicyears->start_end_year ??
                                ($ac->acdemicyears->start_year . '/' . $ac->acdemicyears->end_year);
                        }
                        $academicCourses[$ac->id] = $academicYear;
                    }
                } catch (\Exception $e) {
                    \Log::warning('Error fetching academic courses, continuing without academic year data: ' . $e->getMessage());
                    // Continue without academic year data
                }
            }

            $result = $slots->map(function ($slot) use ($academicCourses) {
                try {
                    $courseName = optional($slot->courses)->name ?? 'N/A';
                    $className = $slot->class_name ?? 'N/A';
                    $locationName = optional($slot->locations)->name ?? 'N/A';
                    $weekdayName = optional($slot->weekDays)->name ?? 'N/A';
                    $academicYear = $academicCourses[$slot->academic_course_id] ?? 'N/A';

                    return [
                        'id' => $slot->id,
                        'course_name' => $courseName,
                        'academic_year' => $academicYear,
                        'location' => $locationName,
                        'weekday' => $weekdayName,
                        'start_time' => $slot->start_time,
                        'end_time' => $slot->end_time,
                        'class_name' => $className,
                        'display_name' => sprintf(
                            '%s - %s - %s (%s %s-%s)',
                            $courseName,
                            $className,
                            $weekdayName,
                            $locationName,
                            $slot->start_time,
                            $slot->end_time
                        ),
                    ];
                } catch (\Exception $e) {
                    \Log::warning('Error mapping slot ' . $slot->id . ': ' . $e->getMessage());
                    // Return basic data if mapping fails
                    return [
                        'id' => $slot->id,
                        'course_name' => 'N/A',
                        'academic_year' => 'N/A',
                        'location' => 'N/A',
                        'weekday' => 'N/A',
                        'start_time' => $slot->start_time ?? 'N/A',
                        'end_time' => $slot->end_time ?? 'N/A',
                        'class_name' => $slot->class_name ?? 'N/A',
                        'display_name' => 'Slot ' . $slot->id,
                    ];
                }
            })
                ->values(); // Reset array keys and convert to array

            \Log::info('Course time slots processed successfully', [
                'result_count' => $result->count()
            ]);

            return $result;
        } catch (\Exception $e) {
            \Log::error('Error in getAllForAnnouncements: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return collect([]);
        }
    }
}
