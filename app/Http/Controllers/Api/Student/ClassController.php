<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\ManageStudentRecord;
use Illuminate\Http\JsonResponse;

class ClassController extends Controller
{
    /**
     * List classes (classrooms) for the logged-in student's enrolled courses.
     * Returns upcoming, ongoing, and ended classes with start_time, end_time, and status.
     */
    public function index(): JsonResponse
    {
        $userId = auth()->id();
        $courseIds = ManageStudentRecord::where('buyer_id', $userId)
            ->pluck('course_id')
            ->unique()
            ->values()
            ->toArray();

        if (empty($courseIds)) {
            return response()->json([
                'success' => true,
                'data' => [],
                'message' => 'No enrolled courses.',
            ]);
        }

        $classrooms = Classroom::whereIn('course_id', $courseIds)
            ->with(['course:id,name', 'tutor:id,first_name,last_name'])
            ->orderBy('start_time')
            ->get()
            ->map(function (Classroom $classroom) {
                return [
                    'id' => $classroom->id,
                    'name' => $classroom->name,
                    'room_code' => $classroom->room_code,
                    'description' => $classroom->description,
                    'schedule_summary' => $classroom->schedule_summary,
                    'start_time' => $classroom->start_time?->toIso8601String(),
                    'end_time' => $classroom->end_time?->toIso8601String(),
                    'status' => $classroom->status,
                    'course' => $classroom->course ? [
                        'id' => $classroom->course->id,
                        'name' => $classroom->course->name,
                    ] : null,
                    'tutor' => $classroom->tutor ? [
                        'id' => $classroom->tutor->id,
                        'full_name' => $classroom->tutor->full_name,
                    ] : null,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $classrooms,
        ]);
    }
}
