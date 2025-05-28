<?php

namespace App\Http\Controllers\Api\Admin\Assign;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\FetchWeeksRequest;
use App\Http\Requests\Api\Admin\StoreAssigmentRequest;

use App\Models\AcdemicCourse;
use App\Models\Course;
use App\Models\CourseAssignment;
use App\Models\Week;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AssignmentController extends Controller
{
    /**
     * Store a newly created resource in storage.
     *
     * @param  StoreAssigmentRequest  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function index()
    {
        try {

            $courseAssignment = CourseAssignment::with('weeks', 'acdemicCourses.courses', 'acdemicCourses.acdemicyears')
                ->whereHas('acdemicCourses.courses', function ($query) {
                    $query->where('created_id', auth()->id());
                })
                ->orderBy('created_at', 'desc')
                ->get()
                ->transform(function ($assignment) {
           
                    return [
                        'acdemicyear' => $assignment?->acdemicyears?->first() ? $assignment?->acdemicyears?->first()->start_end_year : null,
                        'course_name' => $assignment?->acdemicCourses?->courses?->name ?? null,
                        'course_slug' => $assignment?->acdemicCourses?->courses?->slug ?? null,
                        'week_number' => $assignment?->weeks?->first() ? $assignment?->weeks->first()?->week_number : null,
                        'available_weeks' => $assignment?->weeks?->first() ? $assignment?->weeks?->first()?->start_end_date : null,
                    ];
                });
            $response = [
                'success' => true,
                'message' => 'Courses fetched successfully.',
                'data' => $courseAssignment,

            ];
            return response()->json($response, 200);
        } catch (\Exception $e) {
            Log::error("Failed to fetch assignments. Message => {$e->getMessage()}, File => {$e->getFile()}, Line => {$e->getLine()}, Code => {$e->getCode()}.");
            return sendError('error', ['error' => 'An error occurred during fetch.'], 500);
        }
    }
    public function store(StoreAssigmentRequest $request)
    {
        try {
            $existing = CourseAssignment::where('acdemic_course_id', $request->acdemic_course_id)
                ->whereIn('week_id', $request->week_ids)
                ->pluck('week_id')
                ->toArray();

            $newAssignments = collect($request->week_ids)
                ->reject(fn($weekId) => in_array($weekId, $existing))
                ->map(function ($weekId) use ($request) {
                    return [
                        'week_id' => $weekId,
                        'acdemic_course_id' => $request->acdemic_course_id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                })->values()->toArray();

            if (!empty($newAssignments)) {
                CourseAssignment::insert($newAssignments);
            }

            $response = [
                'success' => true,
                'message' => 'Course date assign  for contents created successfully.',
            ];
            return response()->json($response, 201);
        } catch (\Exception $e) {
            Log::error("Failed to create course assignment. Message => {$e->getMessage()}, File => {$e->getFile()}, Line => {$e->getLine()}, Code => {$e->getCode()}.");
            return sendError('error', ['error' => 'An error occurred during store.'], 500);
        }
    }

    public function courseAcdemicRecords()
    {
        try {
            $courses = Course::with([
                'acdemicyears' => function ($query) {
                    $query->withPivot('id');
                }
            ])->get();

            $results = [];

            foreach ($courses as $course) {
                foreach ($course->acdemicyears as $year) {
                    $results[] = [
                        'id' => $year->pivot->id,
                        'name' => "{$course->name} - {$year->start_end_year}",
                    ];
                }
            }
            if (empty($results)) {
                $message = 'No records found.';
            } else {
                $message = 'Fetched Successfully!!';
            }
            $response = [
                'success' => true,
                'data' => $results,
                'message' => $message,
            ];
            return response()->json($response, 200);
        } catch (\Exception $e) {
            Log::error("Failed to fetch course acdemic records. Message => {$e->getMessage()}, File => {$e->getFile()}, Line => {$e->getLine()}, Code => {$e->getCode()}.");
            return sendError('error', ['error' => 'An error occurred during store.'], 500);
        }
    }
    public function courseAcdemicBasedRemainingWeeks(FetchWeeksRequest $request)
    {
        try {
            $data = collect();

            // Get the academic year ID from the acdemic_course table
            $academicCourse = AcdemicCourse::select('acdemic_id')
                ->find($request->input('acdemic_course_id'));

            if (!$academicCourse) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'message' => 'No academic course record found.',
                ]);
            }

            $assignedWeekIds = CourseAssignment::where('acdemic_course_id', $request->acdemic_course_id)
                ->pluck('week_id')
                ->toArray();

            // Get unassigned weeks for the academic year
            $weeks = Week::select('start_date', 'end_date', 'week_number', 'id')
                ->where('academic_year_id', $academicCourse->acdemic_id)
                ->when(!empty($assignedWeekIds), function ($query) use ($assignedWeekIds) {
                    return $query->whereNotIn('id', $assignedWeekIds);
                })
                ->get();

            $data = $weeks->map(function ($week) {
                return [
                    'id' => $week->id,
                    'name' => "{$week->week_number} - " .
                        \Carbon\Carbon::parse($week->start_date)->format('d M') .
                        " to " .
                        \Carbon\Carbon::parse($week->end_date)->format('d M'),
                ];
            });

            $message = $data->isEmpty() ? 'No record found' : 'Fetched Successfully!!';

            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => $message,
            ], 200);
        } catch (\Exception $e) {
            Log::error("Failed to fetch weeks record. Message => {$e->getMessage()}, File => {$e->getFile()}, Line => {$e->getLine()}, Code => {$e->getCode()}.");
            return sendError('error', ['error' => 'An error occurred during fetch.'], 500);
        }
    }
    public function courseAcdemicBasedWeeks(FetchWeeksRequest $request)
    {
        try {
            $data = collect();

            // Get the academic year ID from the acdemic_course table
            $academicCourse = DB::table('acdemic_course')
                ->select('acdemic_id')
                ->where('id', $request->acdemic_course_id)
                ->first();

            if (!$academicCourse) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'message' => 'No academic course found.',
                ]);
            }

            $subjects = AcdemicCourse::with('courses:id', 'courses.subjects:id,name')->where('id', $request->acdemic_course_id)->select('id', 'course_id')->get();

            $assignments = CourseAssignment::with('weeks:id,start_date,end_date,week_number')->where('acdemic_course_id', $request->acdemic_course_id)->select('id', 'week_id')->get();

            if ($assignments->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'message' => 'No course assignment week assign  yet.',
                ]);
            }

            if ($subjects->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'message' => 'No course subject assign yet',
                ]);
            }

            $data['subjects'] = $subjects->flatMap(function ($item) {
                $courseSubjects = $item['courses']['subjects'];
                return $courseSubjects->map(function ($subject) {
                    return [
                        'id' => $subject['id'],
                        'name' => $subject['name'],
                    ];
                });
            })->values();

            $data['assignments'] = $assignments->map(function ($assignment) {
                $week = $assignment['weeks'];

                return [
                    'id' => $assignment['id'],
                    'name' => "{$week['week_number']} - " .
                        \Carbon\Carbon::parse($week['start_date'])->format('d M') .
                        " to " .
                        \Carbon\Carbon::parse($week['end_date'])->format('d M'),
                ];
            });


            $message = $data->isEmpty() ? 'No record found' : 'Fetched Successfully!!';

            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => $message,
            ], 200);
        } catch (\Exception $e) {
            Log::error("Failed to fetch weeks record. Message => {$e->getMessage()}, File => {$e->getFile()}, Line => {$e->getLine()}, Code => {$e->getCode()}.");
            return sendError('error', ['error' => 'An error occurred during fetch.'], 500);
        }
    }
}
