<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Student\CAssignmentRequest;

use App\Http\Requests\Api\Student\SubTopicIdRequest;
use App\Http\Requests\Api\Student\TestIdRequest;
use App\Http\Requests\Api\Student\TopicIdRequest;
use App\Models\Course;
use App\Models\CourseAssignment;
use App\Models\CourseSubTopic;
use App\Models\CourseTest;
use App\Models\CourseTopic;
use App\Models\ManageStudentRecord;
use App\Models\User;
use Illuminate\Support\Facades\Response;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class AssignmentController extends Controller {

    // public function currentAssignment(CAssignmentRequest $request)
    // {
    //     $userId = auth()->user()->id;
    //     $subjectId = $request->subject_id;
    //     $assignmentIds = array();
    //     $courseIds = array();

    //     $date = Carbon::parse('2024-12-30 00:00:00');
    //     $chooseTitle = $request->choose_title;

    //     $course = Course::with('manageStudentRecord:id,model_id,model_type')
    //         ->whereHas(
    //             'manageStudentRecord',
    //             function ($q) use ($userId) {
    //                 return $q->where('buyer_id', $userId);
    //             }
    //         )

    //         ->select('id')
    //         ->get();

    //     if ($course->isEmpty()) {
    //         $response = [
    //             'success' => true,
    //             'message' => 'No course found.',
    //             'data' => [],
    //         ];
    //         return response()->json($response, 404);
    //     }

    //     $courseIds = $course->flatMap(function ($item) {
    //         return $item->manageStudentRecord->pluck('id');
    //     })->values(); //fetch the coursedIds from manage_student_records table.

    //     $assignment = CourseAssignment::with('manageStudentRecord', 'weeks')

    //         ->whereHas('manageStudentRecord', function ($q) use ($courseIds) {
    //             $q->whereIn('parent_id', $courseIds);
    //         })

    //         ->whereHas('weeks', function ($q) use ($date) {
    //             $q->where('start_date', '<=', $date)
    //                 ->where('end_date', '>=', $date);
    //         })
    //         ->get();

    //     if ($assignment->isEmpty()) {
    //         $response = [
    //             'success' => true,
    //             'message' => 'No assignment found for this course.',
    //             'data' => [],
    //         ];
    //         return response()->json($response, 404);
    //     }

    //     $assignmentIds = $assignment->flatMap(function ($item) {
    //         return $item->manageStudentRecord->pluck('id');
    //     })->unique()->values();


    //     if (config('constants.assignment_content.' . $chooseTitle) == 'App\Models\CourseTopic') {
    //         $topicContent = CourseTopic::with('manageStudentRecord')
    //             ->whereHas('manageStudentRecord', function ($q) use ($assignmentIds) {
    //                 $q->whereIn('parent_id', $assignmentIds);
    //             })
    //             ->where('subject_id', $subjectId)
    //             ->paginate();


    //         $topicContent->through(function ($item) {
    //             $record = $item->manageStudentRecord->first(); // Assuming only one per topic per student
    //             return [
    //                 'id' => $item->id,
    //                 'name' => $item->name,
    //                 'completed' => optional($record)->is_completed !== null
    //                     ? config('constants.completed_reverse.' . optional($record)->is_completed)
    //                     : null,
    //                 'completed_at' => optional($record)->completed_at,
    //             ];
    //         });

    //         $response = [
    //             'success' => true,
    //             'message' => $topicContent->total() ? 'Topic content fetched successfully.'
    //                 : 'No Topic content record found.',
    //             'data' => $topicContent,
    //         ];

    //         return response()->json($response, $topicContent->total() ? 200 : 404);
    //     }//fetch the Topic content 

    //     if (config('constants.assignment_content.' . $chooseTitle) == 'App\Models\CourseSubTopic') {

    //         $topicIds = ManageStudentRecord::whereIn('parent_id', $assignmentIds)->pluck('id')->toArray();

    //         if (empty($topicIds)) {
    //             $response = [
    //                 'success' => true,
    //                 'message' => 'Topics are not found.',
    //                 'data' => [],
    //             ];
    //             return response()->json($response, 404);
    //         }

    //         $subTopicContent = CourseSubTopic::with('courseTopic', 'manageStudentRecord')
    //             ->whereHas('manageStudentRecord', function ($q) use ($topicIds) {
    //                 $q->whereIn('parent_id', $topicIds);
    //             })
    //             ->whereHas('courseTopic', function ($q) use ($subjectId) {
    //                 $q->where('subject_id', $subjectId);
    //             })
    //             ->paginate();

    //         $subTopicContent->through(function ($item) {
    //             $record = $item->manageStudentRecord->first();

    //             return [
    //                 'id' => $item->id,
    //                 'sub_topic_name' => $item->name,
    //                 'course_name' => optional($item->courseTopic)->name,
    //                 'completed' => optional($record)->is_completed !== null
    //                     ? config('constants.completed_reverse.' . optional($record)->is_completed)
    //                     : null,
    //                 'completed_at' => optional($record)->completed_at,
    //             ];
    //         });
    //         $response = [
    //             'success' => true,
    //             'message' => $subTopicContent->total() ? 'SubTopic Content fetched successfully.'
    //                 : 'No subtopic record found.',
    //             'data' => $subTopicContent,
    //         ];

    //         return response()->json($response, $subTopicContent->total() ? 200 : 404);
    //     }

    //     if (config('constants.assignment_content.' . $chooseTitle) == 'App\Models\CourseTopicTest') {

    //         $topicIds = ManageStudentRecord::whereIn('parent_id', $assignmentIds)->pluck('id')->toArray();

    //         if (empty($topicIds)) {
    //             $response = [
    //                 'success' => true,
    //                 'message' => 'Topics are not found.',
    //                 'data' => [],
    //             ];
    //             return response()->json($response, 404);
    //         }

    //         $topicTest = CourseTest::with('courseTopic', 'manageStudentRecord')

    //             ->whereHas('courseTopic', function ($q) use ($subjectId) {
    //                 $q->where('subject_id', $subjectId);
    //             })
    //             ->whereHas('manageStudentRecord', function ($q) use ($topicIds) {
    //                 $q->whereIn('parent_id', $topicIds);
    //             })
    //             ->whereNull('course_sub_topic_id')
    //             ->paginate();

    //         $topicTest->through(function ($item) {
    //             $record = $item->manageStudentRecord->first();

    //             return [
    //                 'id' => $item->id,
    //                 'test_name' => $item->name,
    //                 'course_name' => optional($item->courseTopic)->name,

    //                 'completed' => optional($record)->is_completed !== null
    //                     ? config('constants.completed_reverse.' . optional($record)->is_completed)
    //                     : null,
    //                 'completed_at' => optional($record)->completed_at,
    //             ];
    //         });
    //         $response = [
    //             'success' => true,
    //             'message' => $topicTest->total() ? 'Topic test  fetched successfully.'
    //                 : 'No topic test record found.',
    //             'data' => $topicTest,
    //         ];

    //         return response()->json($response, $topicTest->total() ? 200 : 404);
    //     }

    //     if (config('constants.assignment_content.' . $chooseTitle) == 'App\Models\CourseSubTopicTest') {

    //         $topicIds = ManageStudentRecord::whereIn('parent_id', $assignmentIds)->pluck('id')->toArray();

    //         if (empty($topicIds)) {
    //             $response = [
    //                 'success' => true,
    //                 'message' => 'Topics are not found.',
    //                 'data' => [],
    //             ];
    //             return response()->json($response, 404);
    //         }

    //         $subTopicIds = ManageStudentRecord::whereIn('parent_id', $topicIds)->pluck('id')->toArray();

    //         $topicTest = CourseTest::with('courseSubTopic.courseTopic', 'manageStudentRecord')

    //             ->whereHas('courseSubTopic.courseTopic', function ($q) use ($subjectId) {
    //                 $q->where('subject_id', $subjectId);
    //             })
    //             ->whereHas('manageStudentRecord', function ($q) use ($subTopicIds) {
    //                 $q->whereIn('parent_id', $subTopicIds);
    //             })
    //             ->paginate();

    //         $topicTest->through(function ($item) {
    //             $record = $item->manageStudentRecord->first();

    //             return [
    //                 'id' => $item->id,
    //                 'test_name' => $item->name,
    //                 'topic_name' => optional($item->courseTopic)->name,
    //                 'sub_topic_name' => optional($item->courseSubTopic)->name,
    //                 'completed' => optional($record)->is_completed !== null
    //                     ? config('constants.completed_reverse.' . optional($record)->is_completed)
    //                     : null,
    //                 'completed_at' => optional($record)->completed_at,
    //             ];
    //         });
    //         $response = [
    //             'success' => true,
    //             'message' => $topicTest->total() ? 'Topic test  fetched successfully.'
    //                 : 'No topic test record found.',
    //             'data' => $topicTest,
    //         ];

    //         return response()->json($response, $topicTest->total() ? 200 : 404);
    //     }
    // }

    public function currentAssignment(CAssignmentRequest $request) {
        try {
            $userId = auth()->id(); 
            $subjectId = $request->subject_id;
            $chooseTitle = $request->choose_title;
            $date = Carbon::now();

            $contentType = config("constants.assignment_content.$chooseTitle");
           
            // Step 1: Get ManageStudentRecord IDs linked to user via Course
            $courseIds = Course::whereHas(
                'manageStudentRecord',
                fn($q) =>
                $q->where('buyer_id', $userId)
            )
                ->with(['manageStudentRecord:id,model_id,model_type'])
                ->get()
                ->flatMap(fn($course) => $course->manageStudentRecord->pluck('id'))
                ->values();

            if ($courseIds->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'No course found.',
                    'data' => [],
                ], 404);
            }
          
            // Step 2: Get Assignment IDs linked to these courses and within date range
            $assignmentIds = CourseAssignment::with('manageStudentRecord', 'weeks')
                ->whereHas('manageStudentRecord', fn($q) => $q->whereIn('parent_id', $courseIds))
                ->whereHas('weeks', fn($q) => $q->where('start_date', '<=', $date)->where('end_date', '>=', $date))
                ->get()
                ->flatMap(fn($assignment) => $assignment->manageStudentRecord->pluck('id'))
                ->unique()
                ->values();
        
            if ($assignmentIds->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'No assignment found for this course.',
                    'data' => [],
                ], 404);
            }

            // Step 3: Dispatch by content type
            switch ($contentType) {
                case CourseTopic::class:
                    return $this->fetchCourseTopics($assignmentIds, $subjectId);

                case CourseSubTopic::class:
                    return $this->fetchCourseSubTopics($assignmentIds, $subjectId);

                case 'App\Models\CourseTopicTest':
                    return $this->fetchTopicTests($assignmentIds, $subjectId);

                case 'App\Models\CourseSubTopicTest':
                    return $this->fetchSubTopicTests($assignmentIds, $subjectId);

                default:
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid content type.',
                    ], 400);
            }
        } catch (\Exception $e) {
            \Log::error("Failed to fetch the current assignment. Message => {$e->getMessage()}, File => {$e->getFile()},  Line No => {$e->getLine()}, Error Code => {$e->getCode()}.");
            return sendError('Error', ['error' => 'An error is occured.'], 500);
        }
    }

    public function topicContentView(TopicIdRequest $request) {
        try {

            $courseTopic = CourseTopic::with('courseTest', 'courseAssignment.weeks')->find($request->input('topic_id'));


            if (!$courseTopic) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid content type.',
                    'data' => [],
                ], 400);
            }
            $week = optional($courseTopic->courseAssignment)->weeks;

            if ($week && Carbon::parse($week->start_date)->isFuture()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Right now, you have no access to this content.',
                    'data' => [],
                ], 400);
            }

            if (!$week) {
                return response()->json([
                    'success' => false,
                    'message' => 'Something went wrong: week is invalid or missing.',
                    'data' => [],
                ], 400);
            }
            dd($courseTopic->getMedia('content_upload'));

            $data = [
                'id' => $courseTopic->id,
                'topic_name' => $courseTopic->name,
                'topic_media' => $courseTopic->getMedia('content_upload')->map(fn($m) => [
                    'url' => $m->getUrl(),
                    'type' => $m->mime_type,
                ]),
                'topic_test' => $courseTopic->courseTest->pluck('name', 'id'),
            ];

            return response()->json([
                'success' => true,
                'message' => 'Data fetched successfully.',
                'data' => $data,
            ], 200);
        } catch (\Exception $e) {
            \Log::error("Failed to fetch the topic. Message => {$e->getMessage()}, File => {$e->getFile()}, Line No => {$e->getLine()}, Error Code => {$e->getCode()}.");

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching the topic.',
                'data' => [],
            ], 500);
        }
    }

    public function topicTest(TestIdRequest $request) {
        try {

            $topicTest = CourseTest::with('courseTopic.courseAssignment.weeks', 'question.options')
                ->whereNull('course_sub_topic_id')
                ->find($request->input('test_id'));

            if (!$topicTest) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid content type.',
                    'data' => [],
                ], 400);
            }
            $week = optional($topicTest->courseTopic->courseAssignment)->weeks;
            $now = Carbon::now();

            if (!$now->between(Carbon::parse($week->start_date), Carbon::parse($week->end_date))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Right now, you have no access to this give the test.',
                    'data' => [],
                ], 400);
            }

            if (!$week) {
                return response()->json([
                    'success' => false,
                    'message' => 'Something went wrong: week is invalid or missing.',
                    'data' => [],
                ], 400);
            }

            $questions = $topicTest->question->map(function ($question) {
                return [
                    'id' => $question->id,
                    'name' => $question->name,
                    'options' => $question->options->map(function ($option) {
                        return [
                            'id' => $option->id,
                            'name' => $option->name,
                        ];
                    }),
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Test data fetched successfully.',
                'data' => [
                    'test' => [
                        'id' => $topicTest->id,
                        'name' => $topicTest->name,
                        'questions' => $questions,
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error("Failed to fetch the topic. Message => {$e->getMessage()}, File => {$e->getFile()}, Line No => {$e->getLine()}, Error Code => {$e->getCode()}.");

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching the topic.',
                'data' => [],
            ], 500);
        }
    }
    public function fetchSubjects() {
        try {
            $user = User::with([
                'course' => function ($q) {
                    $q->whereNull('parent_id')->with('subjects');
                }
            ])->find(Auth::user()->id);

            if ($user && $user->course->isNotEmpty()) {
                $subjects = collect();

                foreach ($user->course as $course) {
                    $subjects = $subjects->merge(
                        $course->subjects->map(function ($subject) {
                            return [
                                'id' => $subject->id,
                                'name' => $subject->name,
                            ];
                        })
                    );
                }

                return response()->json([
                    'success' => true,
                    'data' => $subjects->values(),
                    'message' => 'Subjects Fetched Successfully!!',
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No subjects found.',
                ], 404);
            }
        } catch (\Exception $e) {
            \Log::error("Fetching student subjects failed. Message => {$e->getMessage()}, File => {$e->getFile()}, Line => {$e->getLine()}, Error Code => {$e->getCode()}.");

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching subjects.',
            ], 500);
        }
    }
    public function subTopicContentView(SubTopicIdRequest $request) {
        try {
            $subTopic = CourseSubTopic::with('test', 'courseTopic.courseAssignment.weeks')->find($request->input('sub_topic_id'));

            if (!$subTopic) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid content type.',
                    'data' => [],
                ], 400);
            }

            $week = optional($subTopic->courseTopic->courseAssignment)->weeks;

            if ($week && Carbon::parse($week->start_date)->isFuture()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Right now, you have no access to this content.',
                    'data' => [],
                ], 400);
            }

            if (!$week) {
                return response()->json([
                    'success' => false,
                    'message' => 'Something went wrong: week is invalid or missing.',
                    'data' => [],
                ], 400);
            }


            $data = [
                'id' => $subTopic->id,
                'topic_name' => optional($subTopic->courseTopic)->name,
                'sub_topic_name' => $subTopic->name,
                'sub_topic_media' => $subTopic->getMedia('content_upload')->map(fn($m) => [
                    'url' => $m->getUrl(),
                    'type' => $m->mime_type,
                ]),
                'sub_topic_test' => $subTopic->test->pluck('name', 'id'),
            ];

            return response()->json([
                'success' => true,
                'message' => 'Data fetched successfully.',
                'data' => $data,
            ], 200);
        } catch (\Exception $e) {
            \Log::error("Failed to fetch the subtopic content. Message => {$e->getMessage()}, File => {$e->getFile()}, Line No => {$e->getLine()}, Error Code => {$e->getCode()}.");

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching the subtopic.',
                'data' => [],
            ], 500);
        }
    }

    private function fetchCourseTopics($assignmentIds, $subjectId) {
        $topics = CourseTopic::with('manageStudentRecord')
            ->whereHas('manageStudentRecord', fn($q) => $q->whereIn('parent_id', $assignmentIds))
            ->where('subject_id', $subjectId)
            ->paginate();

        $topics->getCollection()->transform(function ($item) {
            $record = $item->manageStudentRecord->first();
            return [
                'id' => $item->id,
                'name' => $item->name,
                'completed' => optional($record)->is_completed !== null
                    ? config('constants.completed_reverse.' . optional($record)->is_completed)
                    : null,
                'completed_at' => optional($record)->completed_at,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => $topics->total() ? 'Topic content fetched successfully.' : 'No Topic content record found.',
            'data' => $topics,
        ], $topics->total() ? 200 : 404);
    }

    private function fetchCourseSubTopics($assignmentIds, $subjectId) {
        $topicIds = ManageStudentRecord::whereIn('parent_id', $assignmentIds)->pluck('id');

        if ($topicIds->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'Topics are not found.',
                'data' => [],
            ], 404);
        }

        $subTopics = CourseSubTopic::with('courseTopic', 'manageStudentRecord')
            ->whereHas('manageStudentRecord', fn($q) => $q->whereIn('parent_id', $topicIds))
            ->whereHas('courseTopic', fn($q) => $q->where('subject_id', $subjectId))
            ->paginate();

        $subTopics->getCollection()->transform(function ($item) {
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

        return response()->json([
            'success' => true,
            'message' => $subTopics->total() ? 'SubTopic Content fetched successfully.' : 'No subtopic record found.',
            'data' => $subTopics,
        ], $subTopics->total() ? 200 : 404);
    }

    private function fetchTopicTests($assignmentIds, $subjectId) {
        $topicIds = ManageStudentRecord::whereIn('parent_id', $assignmentIds)->pluck('id');

        if ($topicIds->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'Topics are not found.',
                'data' => [],
            ], 404);
        }

        $tests = CourseTest::with('courseTopic', 'manageStudentRecord')
            ->whereHas('courseTopic', fn($q) => $q->where('subject_id', $subjectId))
            ->whereHas('manageStudentRecord', fn($q) => $q->whereIn('parent_id', $topicIds))
            ->whereNull('course_sub_topic_id')
            ->paginate();

        $tests->getCollection()->transform(function ($item) {
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

        return response()->json([
            'success' => true,
            'message' => $tests->total() ? 'Topic test fetched successfully.' : 'No topic test record found.',
            'data' => $tests,
        ], $tests->total() ? 200 : 404);
    }

    private function fetchSubTopicTests($assignmentIds, $subjectId) {
        $topicIds = ManageStudentRecord::whereIn('parent_id', $assignmentIds)->pluck('id');

        if ($topicIds->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'Topics are not found.',
                'data' => [],
            ], 404);
        }

        $subTopicIds = ManageStudentRecord::whereIn('parent_id', $topicIds)->pluck('id');

        $tests = CourseTest::with('courseSubTopic.courseTopic', 'manageStudentRecord')
            ->whereHas('courseSubTopic.courseTopic', fn($q) => $q->where('subject_id', $subjectId))
            ->whereHas('manageStudentRecord', fn($q) => $q->whereIn('parent_id', $subTopicIds))
            ->paginate();

        $tests->getCollection()->transform(function ($item) {
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

        return response()->json([
            'success' => true,
            'message' => $tests->total() ? 'Topic test fetched successfully.' : 'No topic test record found.',
            'data' => $tests,
        ], $tests->total() ? 200 : 404);
    }
}
