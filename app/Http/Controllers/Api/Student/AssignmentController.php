<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Student\CAssignmentRequest;

use App\Models\Course;
use App\Models\CourseAssignment;
use App\Models\CourseSubTopic;
use App\Models\CourseTest;
use App\Models\CourseTopic;
use App\Models\ManageStudentRecord;
use Carbon\Carbon;


class AssignmentController extends Controller
{
    // public function currentAssignment(CAssignmentRequest $request) {
    //     $userId = auth()->user()->id;
    //     $subjectId = $request->subject_id;
    //     $chooseTitle = $request->choose_title;



    //     $date = Carbon::parse('2024-12-30 00:00:00'); // or any input datetime


    //     $collectAssignmentIds = ManageStudentRecord::with([
    //         'course.acdemiccourse.assignment' => function ($q) use ($date) {
    //             $q->whereHas('weeks', function ($q) use ($date) {
    //                 $q->where('start_date', '<=', $date)
    //                     ->where('end_date', '>=', $date);
    //             });
    //         },
    //         'course.acdemiccourse.assignment.weeks' => function ($q) use ($date) {
    //             $q->where('start_date', '<=', $date)
    //                 ->where('end_date', '>=', $date);
    //         }
    //     ])
    //         ->whereHas('course.acdemiccourse.assignment.weeks', function ($q) use ($date) {
    //             $q->where('start_date', '<=', $date)
    //                 ->where('end_date', '>=', $date);
    //         })
    //         ->whereNull('parent_id')
    //         ->where('buyer_id', $userId)
    //         ->get()
    //         ->pluck('course')
    //         ->pluck('acdemiccourse')
    //         ->flatten()
    //         ->pluck('assignment')
    //         ->flatten()
    //         ->pluck('id')
    //         ->unique()
    //         ->values(); //get the data for getting the assignment id'

    //     $query = collect();
    //     if ($collectAssignmentIds) {
    //         $query = CourseTopic::with('manageStudentRecords')

    //             ->whereHas('manageStudentRecords', function ($q) use ($userId) {
    //                 return $q->where('buyer_id', $userId);
    //             })
    //             ->whereIn('course_assignment_id', $collectAssignmentIds)
    //             ->where('subject_id', $subjectId);
    //     }

    //     if (config('constants.assignment_content.' . $chooseTitle) == 'App\Models\CourseTopic') {

    //         $topics = $query->paginate(10);

    //         $topics->through(function ($item) {
    //             $record = $item->manageStudentRecords->first(); // Assuming only one per topic per student
    //             return [
    //                 'id' => $item->id,
    //                 'name' => $item->name,
    //                 'completed' => optional($record)->is_completed ? config('constants.completed_reverse.1') : config('constants.completed_reverse.0'),
    //                 'completed_at' => optional($record)->completed_at,
    //             ];
    //         });

    //         $response = [
    //             'success' => true,
    //             'message' => $topics->total() ? 'Courses Topic fetched successfully.'
    //                 : 'No record found.',
    //             'data' => $topics,
    //         ];

    //         return response()->json($response, 200);
    //     }

    //     if (config('constants.assignment_content.' . $chooseTitle) == 'App\Models\CourseSubTopic') {
    //         $topics = $query->get();
    //         $topicIds = $topics->pluck('id')->toArray();

    //         $subTopics = CourseSubTopic::with('courseTopic', 'manageStudentRecords')
    //             ->whereHas('courseTopic', function ($q) use ($collectAssignmentIds, $subjectId) {
    //                 return $q->whereIn('course_assignment_id', $collectAssignmentIds)
    //                     ->where('subject_id', $subjectId);
    //             })
    //             ->whereHas('manageStudentRecords', function ($q) use ($userId) {
    //                 return $q->where('buyer_id', $userId);
    //             })
    //             ->whereIn('course_topic_id', $topicIds)
    //             ->paginate();

    //         $subTopics->through(function ($item) {
    //             $record = $item->manageStudentRecords->first();
    //             return [
    //                 'id' => $item?->id,
    //                 'topic_name' => optional($item->courseTopic)->name,
    //                 'sub_topic_name' => $item->name,
    //                 'completed' => optional($record)->is_completed ? config('constants.completed_reverse.1') : config('constants.completed_reverse.0'),
    //                 'completed_at' => optional($record)->completed_at,
    //             ];
    //         });

    //         $response = [
    //             'success' => true,
    //             'message' => $subTopics->total() ? 'Courses Topic fetched successfully.'
    //                 : 'No record found.',
    //             'data' => $subTopics,
    //         ];

    //         return response()->json($response, 200);
    //     }

    //     if (config('constants.assignment_content.' . $chooseTitle) == 'App\Models\CourseTopicTest') {
    //         $topics = $query->get();

    //         $topicIds = $topics->pluck('id')->toArray();

    //         $topicTest = CourseTest::with('courseTopic', 'courseSubTopic', 'manageStudentRecord')
    //             ->whereHas('courseTopic', function ($q) use ($collectAssignmentIds, $subjectId) {
    //                 return $q->whereIn('course_assignment_id', $collectAssignmentIds)
    //                     ->where('subject_id', $subjectId);
    //             })
    //             ->whereHas('manageStudentRecord', function ($q) use ($userId) {
    //                 return $q->where('buyer_id', $userId);
    //             })
    //             ->whereIn('course_topic_id', $topicIds)
    //             ->whereNull('course_sub_topic_id')
    //             ->paginate();

    //         $topicTest->through(function ($item) {
    //             $record = $item->manageStudentRecord->first();
    //             return [
    //                 'id' => $item?->id,
    //                 'test_name' => $item->name,
    //                 'topic_name' => optional($item->courseTopic)->name,
    //                 'sub_topic_name' => optional($item->courseSubTopic)->name,
    //                 'completed' => optional($record)->is_completed ? config('constants.completed_reverse.1') : config('constants.completed_reverse.0'),
    //                 'completed_at' => optional($record)->completed_at,
    //             ];
    //         });

    //         $response = [
    //             'success' => true,
    //             'message' => $topicTest->total() ? 'Courses Topic fetched successfully.'
    //                 : 'No record found.',
    //             'data' => $topicTest,
    //         ];

    //         return response()->json($response, 200);
    //     }

    //      if (config('constants.assignment_content.' . $chooseTitle) == 'App\Models\CourseSubTopicTest') {
    //         $topics = $query->get();

    //         $topicIds = $topics->pluck('id')->toArray();

    //         $topicTest = CourseTest::with('courseTopic', 'courseSubTopic', 'manageStudentRecord')
    //             ->whereHas('courseTopic', function ($q) use ($collectAssignmentIds, $subjectId) {
    //                 return $q->whereIn('course_assignment_id', $collectAssignmentIds)
    //                     ->where('subject_id', $subjectId);
    //             })
    //             ->whereHas('manageStudentRecord', function ($q) use ($userId) {
    //                 return $q->where('buyer_id', $userId);
    //             })
    //             ->whereIn('course_topic_id', $topicIds)
    //             ->whereNotNull('course_sub_topic_id')
    //             ->paginate();

    //         $topicTest->through(function ($item) {
    //             $record = $item->manageStudentRecord->first();
    //             return [
    //                 'id' => $item?->id,
    //                 'test_name' => $item->name,
    //                 'topic_name' => optional($item->courseTopic)->name,
    //                 'sub_topic_name' => optional($item->courseSubTopic)->name,
    //                 'completed' => optional($record)->is_completed ? config('constants.completed_reverse.1') : config('constants.completed_reverse.0'),
    //                 'completed_at' => optional($record)->completed_at,
    //             ];
    //         });

    //         $response = [
    //             'success' => true,
    //             'message' => $topicTest->total() ? 'Courses Topic fetched successfully.'
    //                 : 'No record found.',
    //             'data' => $topicTest,
    //         ];

    //         return response()->json($response, 200);
    //     }


    // }



    // public function currentAssignment(CAssignmentRequest $request)
    // {
    //     $userId = auth()->id();

    //     $subjectId = $request->subject_id;
    //     $chooseTitle = $request->choose_title;
    //     $date = Carbon::parse('2025-01-13 00:00:00');

    //     $collectAssignmentIds = $this->getAssignmentIdsForDate($userId, $date);

    //     if (!$collectAssignmentIds->count()) {
    //         return response()->json(['success' => false, 'message' => 'No record found.', 'data' => []], 200);
    //     }

    //     $query = CourseTopic::with('manageStudentRecords')
    //         ->whereHas('manageStudentRecords', fn($q) => $q->where('buyer_id', $userId))
    //         ->whereIn('course_assignment_id', $collectAssignmentIds)
    //         ->where('subject_id', $subjectId);

    //     $model = config('constants.assignment_content.' . $chooseTitle);

    //     return match ($model) {
    //         'App\\Models\\CourseTopic' => $this->handleCourseTopic($query),
    //         'App\\Models\\CourseSubTopic' => $this->handleCourseSubTopic($query, $collectAssignmentIds, $subjectId, $userId),
    //         'App\\Models\\CourseTopicTest' => $this->handleCourseTest($query, $collectAssignmentIds, $subjectId, $userId, true),
    //         'App\\Models\\CourseSubTopicTest' => $this->handleCourseTest($query, $collectAssignmentIds, $subjectId, $userId, false),
    //         default => response()->json(['success' => false, 'message' => 'Invalid content type.', 'data' => []], 400),
    //     };
    // }

    private function getAssignmentIdsForDate($userId, $date)
    {
        return ManageStudentRecord::with(['course.acdemiccourse.assignment.weeks' => fn($q) => $q->whereBetween('start_date', [$date, $date])])
            ->whereHas('course.acdemiccourse.assignment.weeks', fn($q) => $q->whereBetween('start_date', [$date, $date]))
            ->whereNull('parent_id')
            ->where('buyer_id', $userId)
            ->get()
            ->pluck('course.acdemiccourse.assignment')
            ->flatten()
            ->pluck('id')
            ->unique()
            ->values();
    }

    private function handleCourseTopic($query)
    {
        $topics = $query->paginate(10)->through(fn($item) => [
            'id' => $item->id,
            'name' => $item->name,
            'completed' => optional($item->manageStudentRecords->first())->is_completed ? config('constants.completed_reverse.1') : config('constants.completed_reverse.0'),
            'completed_at' => optional($item->manageStudentRecords->first())->completed_at,
        ]);

        return response()->json([
            'success' => true,
            'message' => $topics->total() ? 'Courses Topic fetched successfully.' : 'No record found.',
            'data' => $topics,
        ], 200);
    }

    private function handleCourseSubTopic($query, $assignmentIds, $subjectId, $userId)
    {
        $topicIds = $query->pluck('id');

        $subTopics = CourseSubTopic::with('courseTopic', 'manageStudentRecords')
            ->whereHas('courseTopic', fn($q) => $q->whereIn('course_assignment_id', $assignmentIds)->where('subject_id', $subjectId))
            ->whereHas('manageStudentRecords', fn($q) => $q->where('buyer_id', $userId))
            ->whereIn('course_topic_id', $topicIds)
            ->paginate()
            ->through(fn($item) => [
                'id' => $item->id,
                'topic_name' => optional($item->courseTopic)->name,
                'sub_topic_name' => $item->name,
                'completed' => optional($item->manageStudentRecords->first())->is_completed ? config('constants.completed_reverse.1') : config('constants.completed_reverse.0'),
                'completed_at' => optional($item->manageStudentRecords->first())->completed_at,
            ]);

        return response()->json([
            'success' => true,
            'message' => $subTopics->total() ? 'Courses Topic fetched successfully.' : 'No record found.',
            'data' => $subTopics,
        ], 200);
    }

    private function handleCourseTest($query, $assignmentIds, $subjectId, $userId, $isTopicTest = true)
    {

        $topicIds = $query->pluck('id');

        $tests = CourseTest::with('courseTopic', 'courseSubTopic', 'manageStudentRecord')
            ->whereHas('courseTopic', fn($q) => $q->whereIn('course_assignment_id', $assignmentIds)->where('subject_id', $subjectId))
            ->whereHas('manageStudentRecord', fn($q) => $q->where('buyer_id', $userId))
            ->whereIn('course_topic_id', $topicIds)
            ->when($isTopicTest, fn($q) => $q->whereNull('course_sub_topic_id'))
            ->when(!$isTopicTest, fn($q) => $q->whereNotNull('course_sub_topic_id'))
            ->paginate()
            ->through(fn($item) => [
                'id' => $item->id,
                'test_name' => $item->name,
                'topic_name' => optional($item->courseTopic)->name,
                'sub_topic_name' => optional($item->courseSubTopic)->name,
                'completed' => optional($item->manageStudentRecord->first())->is_completed ? config('constants.completed_reverse.1') : config('constants.completed_reverse.0'),
                'completed_at' => optional($item->manageStudentRecord->first())->completed_at,
            ]);

        return response()->json([
            'success' => true,
            'message' => $tests->total() ? 'Courses Topic fetched successfully.' : 'No record found.',
            'data' => $tests,
        ], 200);
    }



    public function currentAssignment(CAssignmentRequest $request)
    {
        $userId = auth()->user()->id;
        $subjectId = $request->subject_id;
        $assignmentIds = array();
        $courseIds = array();

        $date = Carbon::parse('2024-12-30 00:00:00');
        $chooseTitle = $request->choose_title;

        $course = Course::with('manageStudentRecord:id,model_id,model_type')
            ->whereHas(
                'manageStudentRecord',
                function ($q) use ($userId) {
                    return $q->where('buyer_id', $userId);
                }
            )

            ->select('id')
            ->get();

        if ($course->isEmpty()) {
            $response = [
                'success' => true,
                'message' => 'No course found.',
                'data' => [],
            ];
            return response()->json($response, 404);
        }

        $courseIds = $course->flatMap(function ($item) {
            return $item->manageStudentRecord->pluck('id');
        })->values(); //fetch the coursedIds from manage_student_records table.

        $assignment = CourseAssignment::with('manageStudentRecord', 'weeks')

            ->whereHas('manageStudentRecord', function ($q) use ($courseIds) {
                $q->whereIn('parent_id', $courseIds);
            })

            ->whereHas('weeks', function ($q) use ($date) {
                $q->where('start_date', '<=', $date)
                    ->where('end_date', '>=', $date);
            })
            ->get();

        if ($assignment->isEmpty()) {
            $response = [
                'success' => true,
                'message' => 'No assignment found for this course.',
                'data' => [],
            ];
            return response()->json($response, 404);
        }

        $assignmentIds = $assignment->flatMap(function ($item) {
            return $item->manageStudentRecord->pluck('id');
        })->unique()->values();


        if (config('constants.assignment_content.' . $chooseTitle) == 'App\Models\CourseTopic') {
            $topicContent = CourseTopic::with('manageStudentRecord')
                ->whereHas('manageStudentRecord', function ($q) use ($assignmentIds) {
                    $q->whereIn('parent_id', $assignmentIds);
                })
                ->where('subject_id', $subjectId)
                ->paginate();


            $topicContent->through(function ($item) {
                $record = $item->manageStudentRecord->first(); // Assuming only one per topic per student
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'completed' => optional($record)->is_completed !== null
                        ? config('constants.completed_reverse.' . optional($record)->is_completed)
                        : null,
                    'completed_at' => optional($record)->completed_at,
                ];
            });

            $response = [
                'success' => true,
                'message' => $topicContent->total() ? 'Topic content fetched successfully.'
                    : 'No Topic content record found.',
                'data' => $topicContent,
            ];

            return response()->json($response, $topicContent->total() ? 200 : 404);
        }//fetch the Topic content 

        if (config('constants.assignment_content.' . $chooseTitle) == 'App\Models\CourseSubTopic') {

            $topicIds = ManageStudentRecord::whereIn('parent_id', $assignmentIds)->pluck('id')->toArray();

            if (empty($topicIds)) {
                $response = [
                    'success' => true,
                    'message' => 'Topics are not found.',
                    'data' => [],
                ];
                return response()->json($response, 404);
            }

            $subTopicContent = CourseSubTopic::with('courseTopic', 'manageStudentRecord')
                ->whereHas('manageStudentRecord', function ($q) use ($topicIds) {
                    $q->whereIn('parent_id', $topicIds);
                })
                ->whereHas('courseTopic', function ($q) use ($subjectId) {
                    $q->where('subject_id', $subjectId);
                })
                ->paginate();

            $subTopicContent->through(function ($item) {
                $record = $item->manageStudentRecord->first();

                return [
                    'id' => $item->id,
                    'sub_topic_name' => $item->name,
                    'course_name' => optional($item->courseTopic)->name,
                    'completed' => optional($record)->is_completed !== null
                        ? config('constants.completed_reverse.' . optional($record)->is_completed)
                        : null,
                    'completed_at' => optional($record)->completed_at,
                ];
            });
            $response = [
                'success' => true,
                'message' => $subTopicContent->total() ? 'SubTopic Content fetched successfully.'
                    : 'No subtopic record found.',
                'data' => $subTopicContent,
            ];

            return response()->json($response, $subTopicContent->total() ? 200 : 404);
        }

        if (config('constants.assignment_content.' . $chooseTitle) == 'App\Models\CourseTopicTest') {

            $topicIds = ManageStudentRecord::whereIn('parent_id', $assignmentIds)->pluck('id')->toArray();

            if (empty($topicIds)) {
                $response = [
                    'success' => true,
                    'message' => 'Topics are not found.',
                    'data' => [],
                ];
                return response()->json($response, 404);
            }

            $topicTest = CourseTest::with('courseTopic', 'manageStudentRecord')

                ->whereHas('courseTopic', function ($q) use ($subjectId) {
                    $q->where('subject_id', $subjectId);
                })
                ->whereHas('manageStudentRecord', function ($q) use ($topicIds) {
                    $q->whereIn('parent_id', $topicIds);
                })
                ->whereNull('course_sub_topic_id')
                ->paginate();

            $topicTest->through(function ($item) {
                $record = $item->manageStudentRecord->first();

                return [
                    'id' => $item->id,
                    'test_name' => $item->name,
                    'course_name' => optional($item->courseTopic)->name,

                    'completed' => optional($record)->is_completed !== null
                        ? config('constants.completed_reverse.' . optional($record)->is_completed)
                        : null,
                    'completed_at' => optional($record)->completed_at,
                ];
            });
            $response = [
                'success' => true,
                'message' => $topicTest->total() ? 'Topic test  fetched successfully.'
                    : 'No topic test record found.',
                'data' => $topicTest,
            ];

            return response()->json($response, $topicTest->total() ? 200 : 404);
        }

        if (config('constants.assignment_content.' . $chooseTitle) == 'App\Models\CourseSubTopicTest') {

            $topicIds = ManageStudentRecord::whereIn('parent_id', $assignmentIds)->pluck('id')->toArray();

            if (empty($topicIds)) {
                $response = [
                    'success' => true,
                    'message' => 'Topics are not found.',
                    'data' => [],
                ];
                return response()->json($response, 404);
            }

            $subTopicIds = ManageStudentRecord::whereIn('parent_id', $topicIds)->pluck('id')->toArray();

            $topicTest = CourseTest::with('courseSubTopic.courseTopic', 'manageStudentRecord')

                ->whereHas('courseSubTopic.courseTopic', function ($q) use ($subjectId) {
                    $q->where('subject_id', $subjectId);
                })
                ->whereHas('manageStudentRecord', function ($q) use ($subTopicIds) {
                    $q->whereIn('parent_id', $subTopicIds);
                })
                ->get();
                dd($topicTest->toArray());
            $topicTest->through(function ($item) {
                $record = $item->manageStudentRecord->first();

                return [
                    'id' => $item->id,
                    'test_name' => $item->name,
                    'topic_name' => optional($item->courseTopic)->name,
                    'sub_topic_name' => optional($item->courseSubTopic)->name,
                    'completed' => optional($record)->is_completed !== null
                        ? config('constants.completed_reverse.' . optional($record)->is_completed)
                        : null,
                    'completed_at' => optional($record)->completed_at,
                ];
            });
            $response = [
                'success' => true,
                'message' => $topicTest->total() ? 'Topic test  fetched successfully.'
                    : 'No topic test record found.',
                'data' => $topicTest,
            ];

            return response()->json($response, $topicTest->total() ? 200 : 404);
        }
    }
}
// 2024-12-30 00:00:00
// 2025-01-05 22:00:00