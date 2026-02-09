<?php

namespace App\Http\Controllers\Api\Admin\Assign;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\FetchWeeksRequest;
use App\Http\Requests\Api\Admin\FetchWeeksRequestList;
use App\Http\Requests\Api\Admin\StoreAssigmentRequest;
use App\Http\Requests\Api\Admin\UpdateAssignmentRequest;
use App\Http\Requests\Api\Admin\DeleteAssignmentRequest;
use App\Http\Requests\Api\Admin\ToggleAssignmentStatusRequest;

use App\Models\AcdemicCourse;
use App\Models\Course;
use App\Models\CourseAssignment;
use App\Models\CourseTopic;
use App\Models\CourseSubTopic;
use App\Models\CourseTest;
use App\Models\Week;

use Illuminate\Support\Facades\Auth;
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
    public function index(FetchWeeksRequestList $request)
    {
        try {
            // Get assignment IDs and status mapped to week IDs for the given academic course
            $assignmentMap = [];
            $assignmentStatusMap = [];
            if ($request->filled('acdemic_course_id')) {
                $courseAssignments = CourseAssignment::where('acdemic_course_id', $request->input('acdemic_course_id'))
                    ->select('id', 'week_id', 'status')
                    ->get();
                
                foreach ($courseAssignments as $assignment) {
                    $assignmentMap[$assignment->week_id] = $assignment->id;
                    $assignmentStatusMap[$assignment->week_id] = $assignment->status;
                }
            }
            
            $data = Week::select('start_date', 'end_date', 'week_number', 'id')
                ->when($request->has('acdemic_course_id') && $request->has('assigned_weeks'), function ($q) use ($request) {
                    $courseAssignment = CourseAssignment::query();
                    if ($request->filled('acdemic_course_id')) {
                        $courseAssignment->where('acdemic_course_id', $request->input('acdemic_course_id'));
                    }
                    $weekIds = $courseAssignment->pluck('week_id');
                    if ($request->assigned_weeks) {
                        $q->whereIn('id', $weekIds);
                    } else {

                        $q->whereNotIn('id', $weekIds);
                    }
                })
                ->paginate()->through(function ($week) use ($assignmentMap, $assignmentStatusMap) {
                    $assignmentId = $assignmentMap[$week->id] ?? null;
                    $status = $assignmentStatusMap[$week->id] ?? null;
                    
                    // Determine status label
                    $statusLabel = null;
                    if ($status !== null) {
                        if ($status == config('constants.statuses.APPROVED')) {
                            $statusLabel = 'approved';
                        } elseif ($status == config('constants.statuses.REJECTED')) {
                            $statusLabel = 'rejected';
                        } else {
                            $statusLabel = 'pending';
                        }
                    }
                    
                    $response = [
                        'week_number' => $week->week_number,
                        'start_end_date' => $week->start_end_date,
                        'assignment_id' => $assignmentId,
                        'status' => $status,
                        'status_label' => $statusLabel,
                    ];
                    
                    return $response;
                });


            if ($data->isEmpty()) {
                $message = 'No record found.';

            } else {
                $message = 'Courses  assignment fetched successfully.';
            }
            $response = [
                'success' => true,
                'message' => $message,
                'data' => $data,

            ];
            return response()->json($response, 200);
        } catch (\Exception $e) {
            Log::error("Failed to fetch assignments. Message => {$e->getMessage()}, File => {$e->getFile()}, Line => {$e->getLine()}, Code => {$e->getCode()}.");
            return sendError('error', ['error' => 'An error occurred during fetch.'], 500);
        }
    }

    /**
     * Display the specified assignment.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $assignment = CourseAssignment::with([
                'weeks:id,week_number,start_date,end_date',
                'acdemicCourses:id,course_id,acdemic_id',
                'acdemicCourses.courses:id,name',
                'acdemicCourses.acdemicyears:id,start_year,end_year'
            ])->findOrFail($id);

            $data = [
                'id' => $assignment->id,
                'acdemic_course_id' => $assignment->acdemic_course_id,
                'week_id' => $assignment->week_id,
                'week_details' => $assignment->weeks ? [
                    'id' => $assignment->weeks->id,
                    'week_number' => $assignment->weeks->week_number,
                    'start_date' => $assignment->weeks->start_date,
                    'end_date' => $assignment->weeks->end_date,
                    'start_end_date' => $assignment->weeks->start_end_date ?? null,
                ] : null,
                'academic_course' => [
                    'id' => $assignment->acdemicCourses->id ?? null,
                    'course_name' => $assignment->acdemicCourses->courses->name ?? null,
                    'academic_year' => $assignment->acdemicCourses->acdemicyears 
                        ? ($assignment->acdemicCourses->acdemicyears->start_year . '-' . $assignment->acdemicCourses->acdemicyears->end_year)
                        : null,
                ],
            ];

            return response()->json([
                'success' => true,
                'message' => 'Assignment fetched successfully.',
                'data' => $data,
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return sendError('error', ['error' => 'Assignment not found.'], 404);
        } catch (\Exception $e) {
            Log::error("Failed to fetch assignment. Message => {$e->getMessage()}, File => {$e->getFile()}, Line => {$e->getLine()}, Code => {$e->getCode()}.");
            return sendError('error', ['error' => 'An error occurred while fetching assignment.'], 500);
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
                        \Carbon\Carbon::parse($week->start_date)->format('d M Y') .
                        " to " .
                        \Carbon\Carbon::parse($week->end_date)->format('d M Y'),
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

            $subjects = AcdemicCourse::with([
                'courses:id',
                'courses.subjects' => function ($q) {
                    $q->where('status', config('constants.statuses.APPROVED'))
                        ->select('subjects.id', 'subjects.name');
                },
            ])->where('id', $request->acdemic_course_id)->select('id', 'course_id')->get();

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
                $courseSubjects = $item['courses']['subjects'] ?? collect();
                return $courseSubjects->map(function ($subject) {
                    return [
                        'id' => $subject['id'],
                        'name' => $subject['name'],
                    ];
                });
            })->filter(fn ($s) => !empty($s['id']))->unique('id')->values();

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

    /**
     * Update the specified assignment.
     *
     * @param  UpdateAssignmentRequest  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdateAssignmentRequest $request, $id)
    {
        try {
            Log::info("=== ASSIGNMENT UPDATE STARTED ===", [
                'assignment_id' => $id,
                'request_data' => $request->all(),
                'week_ids' => $request->input('week_ids', []),
                'acdemic_course_id' => $request->input('acdemic_course_id')
            ]);

            DB::beginTransaction();
            Log::info("Transaction begun for assignment ID: {$id}");

            $assignment = CourseAssignment::findOrFail($id);
            Log::info("Assignment found", [
                'assignment_id' => $assignment->id,
                'current_week_id' => $assignment->week_id,
                'current_acdemic_course_id' => $assignment->acdemic_course_id
            ]);
            
            // Get academic course ID from request or existing assignment
            $academicCourseId = $request->input('acdemic_course_id', $assignment->acdemic_course_id);
            $weekIds = $request->input('week_ids', []);

            Log::info("Processing update data", [
                'academic_course_id' => $academicCourseId,
                'week_ids_count' => count($weekIds),
                'week_ids' => $weekIds
            ]);

            // If week_ids are provided, handle multiple assignments
            if (!empty($weekIds)) {
                Log::info("Week IDs provided, processing multiple assignments");

                // Update the existing assignment to use the first week_id
                $oldWeekId = $assignment->week_id;
                $oldAcademicCourseId = $assignment->acdemic_course_id;
                
                $assignment->week_id = $weekIds[0];
                $assignment->acdemic_course_id = $academicCourseId;
                $assignment->save();

                Log::info("Existing assignment updated", [
                    'assignment_id' => $assignment->id,
                    'old_week_id' => $oldWeekId,
                    'new_week_id' => $assignment->week_id,
                    'old_acdemic_course_id' => $oldAcademicCourseId,
                    'new_acdemic_course_id' => $assignment->acdemic_course_id
                ]);

                // Get existing week_ids for this academic course (excluding current assignment)
                $existingWeekIds = CourseAssignment::where('acdemic_course_id', $academicCourseId)
                    ->where('id', '!=', $id)
                    ->pluck('week_id')
                    ->toArray();

                Log::info("Existing week IDs for academic course", [
                    'academic_course_id' => $academicCourseId,
                    'existing_week_ids' => $existingWeekIds,
                    'existing_count' => count($existingWeekIds)
                ]);

                // Create new assignments for remaining week_ids that don't exist
                $newWeekIds = collect($weekIds)
                    ->reject(fn($weekId) => in_array($weekId, $existingWeekIds) || $weekId == $weekIds[0])
                    ->map(function ($weekId) use ($academicCourseId) {
                        return [
                            'week_id' => $weekId,
                            'acdemic_course_id' => $academicCourseId,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    })
                    ->values()
                    ->toArray();

                Log::info("New assignments to create", [
                    'new_week_ids_count' => count($newWeekIds),
                    'new_week_ids' => array_column($newWeekIds, 'week_id')
                ]);

                if (!empty($newWeekIds)) {
                    CourseAssignment::insert($newWeekIds);
                    Log::info("New assignments created successfully", [
                        'created_count' => count($newWeekIds),
                        'created_assignments' => $newWeekIds
                    ]);
                } else {
                    Log::info("No new assignments to create - all week_ids already exist or were used");
                }
            } else {
                Log::info("No week_ids provided, updating only academic course if provided");
                
                // If no week_ids provided, just update academic course if provided
                if ($request->has('acdemic_course_id')) {
                    $oldAcademicCourseId = $assignment->acdemic_course_id;
                    $assignment->acdemic_course_id = $request->acdemic_course_id;
                    $assignment->save();
                    
                    Log::info("Academic course updated", [
                        'assignment_id' => $assignment->id,
                        'old_acdemic_course_id' => $oldAcademicCourseId,
                        'new_acdemic_course_id' => $assignment->acdemic_course_id
                    ]);
                } else {
                    Log::info("No changes to apply - no week_ids and no academic course update");
                }
            }

            DB::commit();
            Log::info("Transaction committed successfully for assignment ID: {$id}");

            // Log final state
            $finalAssignments = CourseAssignment::where('acdemic_course_id', $academicCourseId)
                ->get(['id', 'week_id', 'acdemic_course_id'])
                ->toArray();
            
            Log::info("=== ASSIGNMENT UPDATE COMPLETED ===", [
                'assignment_id' => $id,
                'final_assignments_count' => count($finalAssignments),
                'final_assignments' => $finalAssignments
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Assignment updated successfully.',
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            Log::error("Assignment not found", [
                'assignment_id' => $id,
                'error' => $e->getMessage()
            ]);
            return sendError('error', ['error' => 'Assignment not found.'], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to update assignment", [
                'assignment_id' => $id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'code' => $e->getCode(),
                'trace' => $e->getTraceAsString()
            ]);
            return sendError('error', ['error' => 'An error occurred during update.'], 500);
        }
    }

    /**
     * Remove the specified assignment from storage.
     *
     * @param  DeleteAssignmentRequest  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(DeleteAssignmentRequest $request, $id)
    {
        try {
            Log::info("=== ASSIGNMENT DELETE STARTED ===", [
                'assignment_id' => $id,
                'user_id' => Auth::id()
            ]);

            DB::beginTransaction();
            Log::info("Transaction begun for assignment ID: {$id}");

            $assignment = CourseAssignment::with(['acdemicCourses', 'topics'])->findOrFail($id);
            Log::info("Assignment found for deletion", [
                'assignment_id' => $assignment->id,
                'week_id' => $assignment->week_id,
                'acdemic_course_id' => $assignment->acdemic_course_id
            ]);

            // Get all topics for this assignment
            $topics = CourseTopic::where('course_assignment_id', $id)->get();
            Log::info("Topics found for assignment", [
                'assignment_id' => $id,
                'topics_count' => $topics->count(),
                'topic_ids' => $topics->pluck('id')->toArray()
            ]);

            $deletedTopicsCount = 0;
            $deletedSubtopicsCount = 0;
            $deletedTestsCount = 0;

            // Process each topic
            foreach ($topics as $topic) {
                Log::info("Processing topic for deletion", [
                    'topic_id' => $topic->id,
                    'topic_name' => $topic->name
                ]);

                // Get all subtopics for this topic
                $subtopics = CourseSubTopic::where('course_topic_id', $topic->id)->get();
                Log::info("Subtopics found for topic", [
                    'topic_id' => $topic->id,
                    'subtopics_count' => $subtopics->count(),
                    'subtopic_ids' => $subtopics->pluck('id')->toArray()
                ]);

                // Process each subtopic
                foreach ($subtopics as $subtopic) {
                    Log::info("Processing subtopic for deletion", [
                        'subtopic_id' => $subtopic->id,
                        'subtopic_name' => $subtopic->name
                    ]);

                    // Soft delete all tests for this subtopic
                    $subtopicTests = CourseTest::where('course_sub_topic_id', $subtopic->id)->get();
                    $subtopicTestsCount = $subtopicTests->count();
                    
                    if ($subtopicTestsCount > 0) {
                        CourseTest::where('course_sub_topic_id', $subtopic->id)->delete();
                        $deletedTestsCount += $subtopicTestsCount;
                        Log::info("Tests soft deleted for subtopic", [
                            'subtopic_id' => $subtopic->id,
                            'tests_deleted_count' => $subtopicTestsCount
                        ]);
                    }

                    // Soft delete the subtopic
                    $subtopic->delete();
                    $deletedSubtopicsCount++;
                    Log::info("Subtopic soft deleted", [
                        'subtopic_id' => $subtopic->id
                    ]);
                }

                // Soft delete all tests for this topic (where course_sub_topic_id IS NULL)
                $topicTests = CourseTest::where('course_topic_id', $topic->id)
                    ->whereNull('course_sub_topic_id')
                    ->get();
                $topicTestsCount = $topicTests->count();
                
                if ($topicTestsCount > 0) {
                    CourseTest::where('course_topic_id', $topic->id)
                        ->whereNull('course_sub_topic_id')
                        ->delete();
                    $deletedTestsCount += $topicTestsCount;
                    Log::info("Tests soft deleted for topic", [
                        'topic_id' => $topic->id,
                        'tests_deleted_count' => $topicTestsCount
                    ]);
                }

                // Soft delete the topic
                $topic->delete();
                $deletedTopicsCount++;
                Log::info("Topic soft deleted", [
                    'topic_id' => $topic->id
                ]);
            }

            // Soft delete the assignment itself
            $assignment->delete();
            Log::info("Assignment soft deleted", [
                'assignment_id' => $id
            ]);

            DB::commit();
            Log::info("Transaction committed successfully for assignment ID: {$id}");

            Log::info("=== ASSIGNMENT DELETE COMPLETED ===", [
                'assignment_id' => $id,
                'topics_deleted' => $deletedTopicsCount,
                'subtopics_deleted' => $deletedSubtopicsCount,
                'tests_deleted' => $deletedTestsCount
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Assignment deleted successfully.',
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            Log::error("Assignment not found for deletion", [
                'assignment_id' => $id,
                'error' => $e->getMessage()
            ]);
            return sendError('error', ['error' => 'Assignment not found.'], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to delete assignment", [
                'assignment_id' => $id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'code' => $e->getCode(),
                'trace' => $e->getTraceAsString()
            ]);
            return sendError('error', ['error' => 'An error occurred during deletion.'], 500);
        }
    }

    /**
     * Toggle assignment status (activate/inactivate).
     *
     * @param  ToggleAssignmentStatusRequest  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function toggleStatus(ToggleAssignmentStatusRequest $request, $id)
    {
        try {
            Log::info("=== ASSIGNMENT TOGGLE STATUS STARTED ===", [
                'assignment_id' => $id,
                'action' => $request->input('action'),
                'user_id' => Auth::id()
            ]);

            $assignment = CourseAssignment::findOrFail($id);
            $currentStatus = $assignment->status;
            
            $activeStatus = config('constants.statuses.APPROVED'); // 2
            $inactiveStatus = config('constants.statuses.REJECTED'); // 3

            $statusUpdated = false;
            $newStatus = $currentStatus;
            $statusLabel = '';

            $action = $request->input('action');

            Log::info("Current assignment status", [
                'assignment_id' => $id,
                'current_status' => $currentStatus,
                'requested_action' => $action
            ]);

            if ($action === 'activate') {
                if ($currentStatus != $activeStatus) {
                    $assignment->status = $activeStatus;
                    $assignment->save();
                    $statusUpdated = true;
                    $newStatus = $activeStatus;
                    $statusLabel = 'active';
                    Log::info("Assignment status updated to active", [
                        'assignment_id' => $id,
                        'previous_status' => $currentStatus,
                        'new_status' => $newStatus
                    ]);
                } else {
                    $statusLabel = 'active';
                    Log::info("Assignment is already active", [
                        'assignment_id' => $id,
                        'current_status' => $currentStatus
                    ]);
                }
            } elseif ($action === 'deactivate') {
                if ($currentStatus != $inactiveStatus) {
                    $assignment->status = $inactiveStatus;
                    $assignment->save();
                    $statusUpdated = true;
                    $newStatus = $inactiveStatus;
                    $statusLabel = 'inactive';
                    Log::info("Assignment status updated to inactive", [
                        'assignment_id' => $id,
                        'previous_status' => $currentStatus,
                        'new_status' => $newStatus
                    ]);
                } else {
                    $statusLabel = 'inactive';
                    Log::info("Assignment is already inactive", [
                        'assignment_id' => $id,
                        'current_status' => $currentStatus
                    ]);
                }
            }

            $message = $statusUpdated 
                ? "Assignment status updated to {$statusLabel} successfully."
                : "Assignment is already {$statusLabel}.";

            Log::info("=== ASSIGNMENT TOGGLE STATUS COMPLETED ===", [
                'assignment_id' => $id,
                'previous_status' => $currentStatus,
                'current_status' => $newStatus,
                'status_label' => $statusLabel,
                'was_updated' => $statusUpdated
            ]);

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'assignment_id' => $assignment->id,
                    'previous_status' => $currentStatus,
                    'current_status' => $newStatus,
                    'status_label' => $statusLabel,
                    'was_updated' => $statusUpdated,
                ],
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error("Assignment not found for toggle status", [
                'assignment_id' => $id,
                'error' => $e->getMessage()
            ]);
            return sendError('error', ['error' => 'Assignment not found.'], 404);
        } catch (\Exception $e) {
            Log::error("Failed to toggle assignment status", [
                'assignment_id' => $id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'code' => $e->getCode()
            ]);
            return sendError('error', ['error' => 'An error occurred while toggling assignment status.'], 500);
        }
    }
}
