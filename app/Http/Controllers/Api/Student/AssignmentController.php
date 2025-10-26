<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Student\CAssignmentRequest;

use App\Http\Requests\Api\Student\SubTopicIdRequest;
use App\Http\Requests\Api\Student\SubTopicTestIdRequest;
use App\Http\Requests\Api\Student\TestIdRequest;
use App\Http\Requests\Api\Student\TopicIdRequest;
use App\Models\Course;
use App\Models\CourseAssignment;
use App\Models\CourseSubTopic;
use App\Http\Requests\Api\Student\SubTopicTestSubmissionRequest;
use App\Http\Requests\Api\Student\TopicTestSubmissionRequest;
use App\Http\Requests\Api\Student\MarkCompletionRequest;
use App\Models\CourseResultUser;
use App\Models\ErrorLog;
use App\Models\UserTestAnswer;
use App\Models\Week;
use App\Models\CourseTopic;
use App\Models\CourseTest;
use App\Models\ManageStudentRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

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
            return errorLog("Failed to fetch the current assignment. Message => {$e->getMessage()}, File => {$e->getFile()},  Line No => {$e->getLine()}, Error Code => {$e->getCode()}. ");
        
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
            return errorLog("Failed to fetch the topic. Message => {$e->getMessage()}, File => {$e->getFile()}, Line No => {$e->getLine()}, Error Code => {$e->getCode()}. ");
          
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

            // if (!$now->between(Carbon::parse($week->start_date), Carbon::parse($week->end_date))) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'Right now, you have no access to this give the test.',
            //         'data' => [],
            //     ], 400);
            // }

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
                    'duration_in_sec' => $question->duration_in_sec,
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
            return errorLog("failed to fetch the topic. Message => {$e->getMessage()}, File => {$e->getFile()}, Line No => {$e->getLine()}, Error Code => {$e->getCode()}. ");
            
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
            return errorLOg("Fetching student subjects failed. Message => {$e->getMessage()}, File => {$e->getFile()}, Line => {$e->getLine()}, Error Code => {$e->getCode()}.");
            
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
            return errorLog("Failed to fetch the subtopic content. Message => {$e->getMessage()}, File => {$e->getFile()}, Line No => {$e->getLine()}, Error Code => {$e->getCode()}. ");
           
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
        $userId = Auth::id();
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

        $tests->getCollection()->transform(function ($item) use ($userId) {
            $record = $item->manageStudentRecord->first();
            
            // Get detailed test progress
            $testProgress = $this->getDetailedTestProgress($item->id, $userId);
            
            // Check if the parent topic is completed
            $topicCompletionRecord = ManageStudentRecord::where([
                'model_type' => 'App\\Models\\CourseTopic',
                'model_id' => $item->courseTopic->id,
                'buyer_id' => $userId
            ])->first();
            
            return [
                'id' => $item->id,
                'test_name' => $item->name,
                'course_name' => optional($item->courseTopic)->name,
                'completed' => optional($record)->is_completed !== null
                    ? config('constants.completed_reverse.' . optional($record)->is_completed)
                    : null,
                'completed_at' => optional($record)->completed_at,
                'topic_completed' => optional($topicCompletionRecord)->is_completed !== null
                    ? config('constants.completed_reverse.' . optional($topicCompletionRecord)->is_completed)
                    : null,
                'topic_completed_at' => optional($topicCompletionRecord)->completed_at,
                'test_progress' => $testProgress
            ];
        });

        return response()->json([
            'success' => true,
            'message' => $tests->total() ? 'Topic test fetched successfully.' : 'No topic test record found.',
            'data' => $tests,
        ], $tests->total() ? 200 : 404);
    }

    private function fetchSubTopicTests($assignmentIds, $subjectId) {
        $userId = Auth::id();
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

        $tests->getCollection()->transform(function ($item) use ($userId) {
            $record = $item->manageStudentRecord->first();
            
            // Get detailed test progress
            $testProgress = $this->getDetailedTestProgress($item->id, $userId);
            
            // Check if the parent subtopic is completed
            $subTopicCompletionRecord = ManageStudentRecord::where([
                'model_type' => 'App\\Models\\CourseSubTopic',
                'model_id' => $item->courseSubTopic->id,
                'buyer_id' => $userId
            ])->first();
            
            // Check if the parent topic is completed
            $topicCompletionRecord = ManageStudentRecord::where([
                'model_type' => 'App\\Models\\CourseTopic',
                'model_id' => $item->courseSubTopic->courseTopic->id,
                'buyer_id' => $userId
            ])->first();
            
            return [
                'id' => $item->id,
                'test_name' => $item->name,
                'topic_name' => optional($item->courseSubTopic->courseTopic)->name,
                'sub_topic_name' => optional($item->courseSubTopic)->name,
                'completed' => optional($record)->is_completed !== null
                    ? config('constants.completed_reverse.' . optional($record)->is_completed)
                    : null,
                'completed_at' => optional($record)->completed_at,
                'sub_topic_completed' => optional($subTopicCompletionRecord)->is_completed !== null
                    ? config('constants.completed_reverse.' . optional($subTopicCompletionRecord)->is_completed)
                    : null,
                'sub_topic_completed_at' => optional($subTopicCompletionRecord)->completed_at,
                'topic_completed' => optional($topicCompletionRecord)->is_completed !== null
                    ? config('constants.completed_reverse.' . optional($topicCompletionRecord)->is_completed)
                    : null,
                'topic_completed_at' => optional($topicCompletionRecord)->completed_at,
                'test_progress' => $testProgress
            ];
        });

        return response()->json([
            'success' => true,
            'message' => $tests->total() ? 'Sub topic test fetched successfully.' : 'No sub topic test record found.',
            'data' => $tests,
        ], $tests->total() ? 200 : 404);
    }
    public function subTopicTest(SubTopicTestIdRequest $request) {
        try {
            $subTopicTest = CourseTest::with('courseSubTopic.courseTopic.courseAssignment.weeks', 'question.options')
                ->whereNotNull('course_sub_topic_id')
                ->find($request->input('sub_topic_test_id'));

            if (!$subTopicTest) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid sub topic test.',
                    'data' => [],
                ], 400);
            }

            $week = optional($subTopicTest->courseSubTopic->courseTopic->courseAssignment)->weeks;
            $now = Carbon::now();

            // if (!$now->between(Carbon::parse($week->start_date), Carbon::parse($week->end_date))) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'Right now, you have no access to this give the test.',
            //         'data' => [],
            //     ], 400);
            // }

            if (!$week) {
                return response()->json([
                    'success' => false,
                    'message' => 'Something went wrong: week is invalid or missing.',
                    'data' => [],
                ], 400);
            }

            $questions = $subTopicTest->question->map(function ($question) {
                return [
                    'id' => $question->id,
                    'name' => $question->name,
                    'duration_in_sec' => $question->duration_in_sec,
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
                'message' => 'Sub topic test data fetched successfully.',
                'data' => [
                    'test' => [
                        'id' => $subTopicTest->id,
                        'name' => $subTopicTest->name,
                        'questions' => $questions,
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            return errorLog("Failed to fetch the sub topic test. Message => {$e->getMessage()}, File => {$e->getFile()}, Line No => {$e->getLine()}, Error Code => {$e->getCode()}. ");
           
        }
    }

    public function submitTopicTest(TopicTestSubmissionRequest $request) {
        try {
            $testId = $request->input('test_id');
            $answers = $request->input('answers');
            $userId = Auth::id();

            // Get test with questions and correct answers
            $test = CourseTest::with(['question.options', 'question.correctOptions'])
                ->whereNull('course_sub_topic_id')
                ->find($testId);

            if (!$test) {
                return response()->json([
                    'success' => false,
                    'message' => 'Test not found.',
                    'data' => [],
                ], 404);
            }

            // Check week-based access control
            $week = optional($test->courseTopic->courseAssignment)->weeks;
            $now = Carbon::now();

            if (!$week) {
                return response()->json([
                    'success' => false,
                    'message' => 'Week information not found for this test.',
                    'data' => [],
                ], 400);
            }

            if (!$now->between(Carbon::parse($week->start_date), Carbon::parse($week->end_date))) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot submit this test right now. Test is only available from ' . 
                                Carbon::parse($week->start_date)->format('M d, Y') . ' to ' . 
                                Carbon::parse($week->end_date)->format('M d, Y') . '.',
                    'data' => [],
                ], 403);
            }

            $totalQuestions = $test->question->count();
            $correctAnswers = 0;
            $totalTimeTaken = 0;

            // Check existing attempt
            $existingResult = CourseResultUser::where([
                'student_id' => $userId,
                'test_id' => $testId
            ])->orderBy('attempt_count', 'desc')->first();

            $attemptCount = $existingResult ? $existingResult->attempt_count + 1 : 1;

            // Process each answer
            foreach ($answers as $answer) {
                $questionId = $answer['question_id'];
                $selectedOptionId = $answer['option_id'];
                $timeTaken = $answer['time_taken'];
                $totalTimeTaken += $timeTaken;

                // Find the question
                $question = $test->question->where('id', $questionId)->first();
                if (!$question) continue;

                // Check if selected option is correct
                $isCorrect = $question->correctOptions->contains('id', $selectedOptionId);
                if ($isCorrect) {
                    $correctAnswers++;
                }

                // Store user answer in new table
                UserTestAnswer::create([
                    'user_id' => $userId,
                    'test_id' => $testId,
                    'question_id' => $questionId,
                    'selected_option_id' => $selectedOptionId,
                    'time_taken_seconds' => $timeTaken,
                    'is_correct' => $isCorrect,
                    'attempt_number' => $attemptCount
                ]);
            }

            // Calculate score percentage
            $scorePercentage = $totalQuestions > 0 ? round(($correctAnswers / $totalQuestions) * 100, 2) : 0;
            $isCompleted = ($correctAnswers == $totalQuestions);

            // Store result in course_result_users (create new record for each attempt)
            CourseResultUser::create([
                'student_id' => $userId,
                'test_id' => $testId,
                'test_score' => $scorePercentage,
                'attempt_count' => $attemptCount
            ]);

            // Update ManageStudentRecord if all answers are correct
            if ($isCompleted) {
                ManageStudentRecord::updateOrCreate(
                    [
                        'model_type' => 'App\\Models\\CourseTest',
                        'model_id' => $testId,
                        'buyer_id' => $userId
                    ],
                    [
                        'is_completed' => config('constants.completed.YES'),
                        'completed_at' => now()
                    ]
                );
            }

            return response()->json([
                'success' => true,
                'message' => $isCompleted ? 'Test completed successfully!' : 'Test submitted. You can retry to get all answers correct.',
                'data' => [
                    'score' => $scorePercentage,
                    'correct_answers' => $correctAnswers,
                    'total_questions' => $totalQuestions,
                    'is_completed' => $isCompleted,
                    'attempt_count' => $attemptCount,
                    'total_time_taken' => $totalTimeTaken
                ],
            ], 200);

        } catch (\Exception $e) {
            return errorLog("Failed to submit topic test. Message => {$e->getMessage()}, File => {$e->getFile()}, Line No => {$e->getLine()}, Error Code => {$e->getCode()}. ");
            
        }
    }

    public function submitSubTopicTest(SubTopicTestSubmissionRequest $request) {
        try {
            $testId = $request->input('sub_topic_test_id');
            $answers = $request->input('answers');
            $userId = Auth::id();

            // Get test with questions and correct answers
            $test = CourseTest::with(['question.options', 'question.correctOptions'])
                ->whereNotNull('course_sub_topic_id')
                ->find($testId);

            if (!$test) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sub topic test not found.',
                    'data' => [],
                ], 404);
            }

            // Check week-based access control
            $week = optional($test->courseSubTopic->courseTopic->courseAssignment)->weeks;
            $now = Carbon::now();

            if (!$week) {
                return response()->json([
                    'success' => false,
                    'message' => 'Week information not found for this test.',
                    'data' => [],
                ], 400);
            }

            if (!$now->between(Carbon::parse($week->start_date), Carbon::parse($week->end_date))) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot submit this test right now. Test is only available from ' . 
                                Carbon::parse($week->start_date)->format('M d, Y') . ' to ' . 
                                Carbon::parse($week->end_date)->format('M d, Y') . '.',
                    'data' => [],
                ], 403);
            }

            $totalQuestions = $test->question->count();
            $correctAnswers = 0;
            $totalTimeTaken = 0;

            // Check existing attempt
            $existingResult = CourseResultUser::where([
                'student_id' => $userId,
                'test_id' => $testId
            ])->orderBy('attempt_count', 'desc')->first();

            $attemptCount = $existingResult ? $existingResult->attempt_count + 1 : 1;

            // Process each answer
            foreach ($answers as $answer) {
                $questionId = $answer['question_id'];
                $selectedOptionId = $answer['option_id'];
                $timeTaken = $answer['time_taken'];
                $totalTimeTaken += $timeTaken;

                // Find the question
                $question = $test->question->where('id', $questionId)->first();
                if (!$question) continue;

                // Check if selected option is correct
                $isCorrect = $question->correctOptions->contains('id', $selectedOptionId);
                if ($isCorrect) {
                    $correctAnswers++;
                }

                // Store user answer in new table
                UserTestAnswer::create([
                    'user_id' => $userId,
                    'test_id' => $testId,
                    'question_id' => $questionId,
                    'selected_option_id' => $selectedOptionId,
                    'time_taken_seconds' => $timeTaken,
                    'is_correct' => $isCorrect,
                    'attempt_number' => $attemptCount
                ]);
            }

            // Calculate score percentage
            $scorePercentage = $totalQuestions > 0 ? round(($correctAnswers / $totalQuestions) * 100, 2) : 0;
            $isCompleted = ($correctAnswers == $totalQuestions);

            // Store result in course_result_users (create new record for each attempt)
            CourseResultUser::create([
                'student_id' => $userId,
                'test_id' => $testId,
                'test_score' => $scorePercentage,
                'attempt_count' => $attemptCount
            ]);

            // Update ManageStudentRecord if all answers are correct
            if ($isCompleted) {
                ManageStudentRecord::updateOrCreate(
                    [
                        'model_type' => 'App\\Models\\CourseTest',
                        'model_id' => $testId,
                        'buyer_id' => $userId
                    ],
                    [
                        'is_completed' => config('constants.completed.YES'),
                        'completed_at' => now()
                    ]
                );
            }

            return response()->json([
                'success' => true,
                'message' => $isCompleted ? 'Sub topic test completed successfully!' : 'Sub topic test submitted. You can retry to get all answers correct.',
                'data' => [
                    'score' => $scorePercentage,
                    'correct_answers' => $correctAnswers,
                    'total_questions' => $totalQuestions,
                    'is_completed' => $isCompleted,
                    'attempt_count' => $attemptCount,
                    'total_time_taken' => $totalTimeTaken
                ],
            ], 200);

        } catch (\Exception $e) {
            return errorLog("Failed to submit sub topic test. Message => {$e->getMessage()}, File => {$e->getFile()}, Line No => {$e->getLine()}, Error Code => {$e->getCode()}. ");
       
        }
    }

    /**
     * Mark content as completed
     */
    public function markContentCompleted(MarkCompletionRequest $request)
    {
        try {
            $userId = Auth::id();
            $modelType = $request->input('model_type');
            $modelId = $request->input('model_id');

            // Get the actual model class from config
            $actualModelType = config("constants.assignment_content.{$modelType}");

            if (!$actualModelType) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid model type provided.',
                    'data' => []
                ], 400);
            }

            // Validate if the model exists
            $modelExists = $actualModelType::find($modelId);
            if (!$modelExists) {
                return response()->json([
                    'success' => false,
                    'message' => ucfirst(strtolower(str_replace('Content', '', $modelType))) . ' not found.',
                    'data' => []
                ], 404);
            }

            // Check if already completed
            $existingRecord = ManageStudentRecord::where([
                'model_type' => $actualModelType,
                'model_id' => $modelId,
                'buyer_id' => $userId
            ])->first();

            if ($existingRecord && $existingRecord->is_completed == config('constants.completed.YES')) {
                return response()->json([
                    'success' => true,
                    'message' => 'Content is already completed.',
                    'data' => [
                        'model_type' => $modelType,
                        'model_id' => $modelId,
                        'is_completed' => true,
                        'completed_at' => $existingRecord->completed_at,
                        'status' => 'already_completed'
                    ]
                ], 200);
            }

            // Mark as completed
            $completionRecord = ManageStudentRecord::updateOrCreate(
                [
                    'model_type' => $actualModelType,
                    'model_id' => $modelId,
                    'buyer_id' => $userId
                ],
                [
                    'is_completed' => config('constants.completed.YES'),
                    'completed_at' => now()
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Content marked as completed successfully.',
                'data' => [
                    'model_type' => $modelType,
                    'model_id' => $modelId,
                    'is_completed' => true,
                    'completed_at' => $completionRecord->completed_at,
                    'status' => 'newly_completed'
                ]
            ], 200);

        } catch (\Exception $e) {
            return errorLog("Failed to mark content as completed. Message => {$e->getMessage()}, File => {$e->getFile()}, Line No => {$e->getLine()}, Error Code => {$e->getCode()}.");
          
        }
    }

    /**
     * Get assigned subjects for the authenticated user
     */
    public function getAssignedSubjects()
    {
        try {
            $userId = auth()->id();
            
            // Get user's courses with subjects
            $courses = Course::whereHas(
                'manageStudentRecord',
                fn($q) => $q->where('buyer_id', $userId)
            )
            ->with(['subjects' => function($q) {
                $q->select('subjects.id', 'subjects.name', 'subjects.created_at')
                  ->orderBy('subjects.name');
            }])
            ->get();

            if ($courses->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'No courses found for this user.',
                    'data' => [
                        'subjects' => [],
                        'total_subjects' => 0
                    ]
                ], 200);
            }

            // Extract unique subjects from all courses
            $allSubjects = collect();
            foreach ($courses as $course) {
                $allSubjects = $allSubjects->merge($course->subjects);
            }

            // Get unique subjects by ID
            $uniqueSubjects = $allSubjects->unique('id')->values();

            // Format response
            $subjects = $uniqueSubjects->map(function($subject) {
                return [
                    'id' => $subject->id,
                    'name' => $subject->name,
                    'created_at' => $subject->created_at
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Assigned subjects fetched successfully.',
                'data' => [
                    'subjects' => $subjects,
                    'total_subjects' => $subjects->count()
                ]
            ], 200);

        } catch (\Exception $e) {
            return errorLog("Failed to fetch assigned subjects. Message => {$e->getMessage()}, File => {$e->getFile()}, Line No => {$e->getLine()}, Error Code => {$e->getCode()}.");
        }
    }

    /**
     * Get current assignment detailed statistics
     */
    public function currentAssignmentStats(CAssignmentRequest $request)
    {
        try {
            $userId = auth()->id();
            $subjectId = $request->subject_id;
            $date = Carbon::now();
            
            // Step 1: Get ManageStudentRecord IDs linked to user via Course and filtered by subject
            $coursesQuery = Course::whereHas(
                'manageStudentRecord',
                fn($q) => $q->where('buyer_id', $userId)
            )
                ->whereHas('subjects', fn($q) => $q->where('subjects.id', $subjectId))
                ->with(['manageStudentRecord:id,model_id,model_type', 'subjects']);
                
            $courses = $coursesQuery->get();
            $courseIds = $courses->flatMap(fn($course) => $course->manageStudentRecord->pluck('id'))
                ->values();
         
            if ($courseIds->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'No course found.',
                    'data' => [],
                ], 404);
            }
          
            // Step 2: Get Assignment IDs linked to these courses and within date range (same as currentAssignment)
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
                    'message' => 'No assignment found for this course in current week.',
                    'data' => [],
                ], 404);
            }

            // Get current week details from CourseAssignment
            $currentWeekAssignment = CourseAssignment::with('weeks')
                ->whereHas('manageStudentRecord', fn($q) => $q->whereIn('parent_id', $courseIds))
                ->whereHas('weeks', fn($q) => $q->where('start_date', '<=', $date)->where('end_date', '>=', $date))
                ->first();
            
            $currentWeek = $currentWeekAssignment ? $currentWeekAssignment->weeks : null;
            
            // Get course details (without end_date as it doesn't exist in courses table)
            // Since $courseIds are already ManageStudentRecord IDs linked to courses, 
            // we need to get the actual course IDs first
            $actualCourseIds = ManageStudentRecord::whereIn('id', $courseIds)
                ->where('model_type', 'App\Models\Course')
                ->pluck('model_id');
                
            $courseDetails = Course::whereIn('id', $actualCourseIds)
                ->select('id', 'name', 'created_at')
                ->first();
         
            // Initialize statistics arrays
            $topicsStats = null;
            $subTopicsStats = null;
            $topicTestsStats = null;
            $subTopicTestsStats = null;

            // Check and get statistics for each content type (similar to currentAssignment switch case)
            
            // Check if topics exist for current assignment
            $topicIds = ManageStudentRecord::whereIn('parent_id', $assignmentIds)->pluck('id');
            $hasTopics = CourseTopic::whereHas('manageStudentRecord', fn($q) => $q->whereIn('parent_id', $topicIds))->exists();
            
            if ($hasTopics) {
                $topicsStats = $this->getTopicsStatistics($assignmentIds, $userId, $subjectId);
            }

            // Check if subtopics exist for current assignment
            $subTopicIds = ManageStudentRecord::whereIn('parent_id', $topicIds)->pluck('id');
            $hasSubTopics = CourseSubTopic::whereHas('manageStudentRecord', fn($q) => $q->whereIn('parent_id', $subTopicIds))->exists();
            
            if ($hasSubTopics) {
                $subTopicsStats = $this->getSubTopicsStatistics($assignmentIds, $userId);
            }

            // Check if topic tests exist for current assignment
            $hasTopicTests = CourseTest::whereHas('manageStudentRecord', fn($q) => $q->whereIn('parent_id', $topicIds))
                ->whereNull('course_sub_topic_id')
                ->exists();
                
            if ($hasTopicTests) {
                $topicTestsStats = $this->getTopicTestsStatistics($assignmentIds, $userId);
            }

            // Check if subtopic tests exist for current assignment
            $hasSubTopicTests = CourseTest::whereHas('manageStudentRecord', fn($q) => $q->whereIn('parent_id', $subTopicIds))
                ->whereNotNull('course_sub_topic_id')
                ->exists();
                
            if ($hasSubTopicTests) {
                $subTopicTestsStats = $this->getSubTopicTestsStatistics($assignmentIds, $userId);
            }

            // Calculate overall progress only if we have any statistics
            $overallProgress = $this->calculateOverallProgress($topicsStats, $subTopicsStats, $topicTestsStats, $subTopicTestsStats);
            $response = [
                'success' => true,
                'message' => 'Assignment statistics fetched successfully.',
                'data' => [
                    'course_info' => [
                        'course_name' => $courseDetails->name ?? 'Unknown Course',
                        'end_date' => $currentWeek->end_date ?? null,
                        'days_remaining' => $currentWeek && $currentWeek->end_date ? 
                            $this->formatTimeRemaining(Carbon::parse($currentWeek->end_date)) : null,
                        'course_started' => $courseDetails->created_at ?? null
                    ],
                    'current_week_info' => [
                        'week_id' => $currentWeek->id ?? null,
                        'week_name' => $currentWeek->name ?? 'Current Week',
                        'week_start_date' => $currentWeek->start_date ?? null,
                        'week_end_date' => $currentWeek->end_date ?? null,
                        'days_remaining_in_week' => $currentWeek ? 
                            max(0, $date->diffInDays(Carbon::parse($currentWeek->end_date), false)) : null,
                        'week_progress_percentage' => $currentWeek ? 
                            min(100, max(0, round((($date->diffInDays(Carbon::parse($currentWeek->start_date), false) + 1) / 
                            (Carbon::parse($currentWeek->start_date)->diffInDays(Carbon::parse($currentWeek->end_date), false) + 1)) * 100, 2))) : 0
                    ],
                    'topics_statistics' => $topicsStats ?? [
                        'total_topics' => 0,
                        'completed_topics' => 0,
                        'pending_topics' => 0,
                        'completion_percentage' => 0,
                        'first_completion' => null,
                        'latest_completion' => null,
                        'completed_topics_list' => []
                    ],
                    'subtopics_statistics' => $subTopicsStats ?? [
                        'total_subtopics' => 0,
                        'completed_subtopics' => 0,
                        'pending_subtopics' => 0,
                        'completion_percentage' => 0,
                        'first_completion' => null,
                        'latest_completion' => null,
                        'completed_subtopics_list' => []
                    ],
                    'topic_tests_statistics' => $topicTestsStats ?? [
                        'total_tests' => 0,
                        'attempted_tests' => 0,
                        'pending_tests' => 0,
                        'total_attempts' => 0,
                        'first_attempt' => null,
                        'latest_attempt' => null,
                        'average_score' => 0,
                        'best_score' => 0,
                        'test_details' => []
                    ],
                    'subtopic_tests_statistics' => $subTopicTestsStats ?? [
                        'total_tests' => 0,
                        'attempted_tests' => 0,
                        'pending_tests' => 0,
                        'total_attempts' => 0,
                        'first_attempt' => null,
                        'latest_attempt' => null,
                        'average_score' => 0,
                        'best_score' => 0,
                        'test_details' => []
                    ],
                    'overall_progress' => $overallProgress,
                    'generated_at' => now()->toISOString()
                ]
            ];

            return response()->json($response, 200);

        } catch (\Exception $e) {
            return errorLog("Failed to fetch assignment statistics. Message => {$e->getMessage()}, File => {$e->getFile()}, Line No => {$e->getLine()}, Error Code => {$e->getCode()}.");
        }
    }

    /**
     * Get topics completion statistics
     */
    private function getTopicsStatistics($assignmentIds, $userId, $subjectId = null)
    {
        $topicIds = ManageStudentRecord::whereIn('parent_id', $assignmentIds)->pluck('id');
        
        $topicsQuery = CourseTopic::whereHas('manageStudentRecord', fn($q) => $q->whereIn('parent_id', $topicIds))
            ->with(['manageStudentRecord' => fn($q) => $q->where('buyer_id', $userId)]);
            
        // Add subject filtering if provided
        if ($subjectId) {
            $topicsQuery->where('subject_id', $subjectId);
        }
        
        $topics = $topicsQuery->get();

        $completed = $topics->filter(function($topic) {
            $record = $topic->manageStudentRecord->first();
            return $record && $record->is_completed == config('constants.completed.YES');
        });

        $firstCompleted = $completed->min(function($topic) {
            return $topic->manageStudentRecord->first()->completed_at;
        });

        $latestCompleted = $completed->max(function($topic) {
            return $topic->manageStudentRecord->first()->completed_at;
        });

        return [
            'total_topics' => $topics->count(),
            'completed_topics' => $completed->count(),
            'pending_topics' => $topics->count() - $completed->count(),
            'completion_percentage' => $topics->count() > 0 ? round(($completed->count() / $topics->count()) * 100, 2) : 0,
            'first_completion' => $firstCompleted,
            'latest_completion' => $latestCompleted,
            'completed_topics_list' => $completed->map(function($topic) {
                $record = $topic->manageStudentRecord->first();
                return [
                    'id' => $topic->id,
                    'name' => $topic->name,
                    'completed_at' => $record->completed_at
                ];
            })->values()
        ];
    }

    /**
     * Get subtopics completion statistics
     */
    private function getSubTopicsStatistics($assignmentIds, $userId)
    {
        $topicIds = ManageStudentRecord::whereIn('parent_id', $assignmentIds)->pluck('id');
        $subTopicIds = ManageStudentRecord::whereIn('parent_id', $topicIds)->pluck('id');

        $subTopics = CourseSubTopic::whereHas('manageStudentRecord', fn($q) => $q->whereIn('parent_id', $subTopicIds))
            ->with(['manageStudentRecord' => fn($q) => $q->where('buyer_id', $userId), 'courseTopic'])
            ->get();

        $completed = $subTopics->filter(function($subTopic) {
            $record = $subTopic->manageStudentRecord->first();
            return $record && $record->is_completed == config('constants.completed.YES');
        });

        $firstCompleted = $completed->min(function($subTopic) {
            return $subTopic->manageStudentRecord->first()->completed_at;
        });

        $latestCompleted = $completed->max(function($subTopic) {
            return $subTopic->manageStudentRecord->first()->completed_at;
        });

        return [
            'total_subtopics' => $subTopics->count(),
            'completed_subtopics' => $completed->count(),
            'pending_subtopics' => $subTopics->count() - $completed->count(),
            'completion_percentage' => $subTopics->count() > 0 ? round(($completed->count() / $subTopics->count()) * 100, 2) : 0,
            'first_completion' => $firstCompleted,
            'latest_completion' => $latestCompleted,
            'completed_subtopics_list' => $completed->map(function($subTopic) {
                $record = $subTopic->manageStudentRecord->first();
                return [
                    'id' => $subTopic->id,
                    'name' => $subTopic->name,
                    'topic_name' => $subTopic->courseTopic->name,
                    'completed_at' => $record->completed_at
                ];
            })->values()
        ];
    }

    /**
     * Get topic tests attempt statistics
     */
    private function getTopicTestsStatistics($assignmentIds, $userId)
    {
        $topicIds = ManageStudentRecord::whereIn('parent_id', $assignmentIds)->pluck('id');

        $tests = CourseTest::whereHas('manageStudentRecord', fn($q) => $q->whereIn('parent_id', $topicIds))
            ->whereNull('course_sub_topic_id')
            ->with('courseTopic')
            ->get();

        $totalAttempts = 0;
        $completedTests = 0;
        $allAttempts = collect();
        $testDetails = [];

        foreach ($tests as $test) {
            $attempts = CourseResultUser::where([
                'student_id' => $userId,
                'test_id' => $test->id
            ])->orderBy('attempt_count')->get();

            $totalAttempts += $attempts->count();
            $allAttempts = $allAttempts->merge($attempts);

            if ($attempts->isNotEmpty()) {
                $completedTests++;
                $testDetails[] = [
                    'test_id' => $test->id,
                    'test_name' => $test->name,
                    'topic_name' => $test->courseTopic->name,
                    'total_attempts' => $attempts->count(),
                    'first_attempt' => [
                        'score' => $attempts->first()->test_score,
                        'attempted_at' => $attempts->first()->created_at
                    ],
                    'latest_attempt' => [
                        'score' => $attempts->last()->test_score,
                        'attempted_at' => $attempts->last()->updated_at
                    ],
                    'best_score' => $attempts->max('test_score'),
                    'average_score' => round($attempts->avg('test_score'), 2)
                ];
            }
        }

        return [
            'total_tests' => $tests->count(),
            'attempted_tests' => $completedTests,
            'pending_tests' => $tests->count() - $completedTests,
            'total_attempts' => $totalAttempts,
            'first_attempt' => $allAttempts->isNotEmpty() ? [
                'score' => $allAttempts->min('test_score'),
                'attempted_at' => $allAttempts->min('created_at')
            ] : null,
            'latest_attempt' => $allAttempts->isNotEmpty() ? [
                'score' => $allAttempts->sortByDesc('updated_at')->first()->test_score,
                'attempted_at' => $allAttempts->max('updated_at')
            ] : null,
            'average_score' => $allAttempts->isNotEmpty() ? round($allAttempts->avg('test_score'), 2) : 0,
            'best_score' => $allAttempts->isNotEmpty() ? $allAttempts->max('test_score') : 0,
            'test_details' => $testDetails
        ];
    }

    /**
     * Get subtopic tests attempt statistics
     */
    private function getSubTopicTestsStatistics($assignmentIds, $userId)
    {
        $topicIds = ManageStudentRecord::whereIn('parent_id', $assignmentIds)->pluck('id');
        $subTopicIds = ManageStudentRecord::whereIn('parent_id', $topicIds)->pluck('id');

        $tests = CourseTest::whereHas('manageStudentRecord', fn($q) => $q->whereIn('parent_id', $subTopicIds))
            ->with(['courseSubTopic.courseTopic'])
            ->get();

        $totalAttempts = 0;
        $completedTests = 0;
        $allAttempts = collect();
        $testDetails = [];

        foreach ($tests as $test) {
            $attempts = CourseResultUser::where([
                'student_id' => $userId,
                'test_id' => $test->id
            ])->orderBy('attempt_count')->get();

            $totalAttempts += $attempts->count();
            $allAttempts = $allAttempts->merge($attempts);

            if ($attempts->isNotEmpty()) {
                $completedTests++;
                $testDetails[] = [
                    'test_id' => $test->id,
                    'test_name' => $test->name,
                    'subtopic_name' => $test->courseSubTopic->name,
                    'topic_name' => $test->courseSubTopic->courseTopic->name,
                    'total_attempts' => $attempts->count(),
                    'first_attempt' => [
                        'score' => $attempts->first()->test_score,
                        'attempted_at' => $attempts->first()->created_at
                    ],
                    'latest_attempt' => [
                        'score' => $attempts->last()->test_score,
                        'attempted_at' => $attempts->last()->updated_at
                    ],
                    'best_score' => $attempts->max('test_score'),
                    'average_score' => round($attempts->avg('test_score'), 2)
                ];
            }
        }

        return [
            'total_tests' => $tests->count(),
            'attempted_tests' => $completedTests,
            'pending_tests' => $tests->count() - $completedTests,
            'total_attempts' => $totalAttempts,
            'first_attempt' => $allAttempts->isNotEmpty() ? [
                'score' => $allAttempts->min('test_score'),
                'attempted_at' => $allAttempts->min('created_at')
            ] : null,
            'latest_attempt' => $allAttempts->isNotEmpty() ? [
                'score' => $allAttempts->sortByDesc('updated_at')->first()->test_score,
                'attempted_at' => $allAttempts->max('updated_at')
            ] : null,
            'average_score' => $allAttempts->isNotEmpty() ? round($allAttempts->avg('test_score'), 2) : 0,
            'best_score' => $allAttempts->isNotEmpty() ? $allAttempts->max('test_score') : 0,
            'test_details' => $testDetails
        ];
    }

    /**
     * Calculate overall progress statistics
     */
    private function calculateOverallProgress($topicsStats, $subTopicsStats, $topicTestsStats, $subTopicTestsStats)
    {
        // Handle null values for each statistics type
        $totalItems = ($topicsStats['total_topics'] ?? 0) + ($subTopicsStats['total_subtopics'] ?? 0) + 
                     ($topicTestsStats['total_tests'] ?? 0) + ($subTopicTestsStats['total_tests'] ?? 0);
        
        $completedItems = ($topicsStats['completed_topics'] ?? 0) + ($subTopicsStats['completed_subtopics'] ?? 0) + 
                         ($topicTestsStats['attempted_tests'] ?? 0) + ($subTopicTestsStats['attempted_tests'] ?? 0);

        $overallPercentage = $totalItems > 0 ? round(($completedItems / $totalItems) * 100, 2) : 0;

        return [
            'total_items' => $totalItems,
            'completed_items' => $completedItems,
            'pending_items' => $totalItems - $completedItems,
            'completion_percentage' => $overallPercentage,
            'progress_breakdown' => [
                'topics' => $topicsStats['completion_percentage'] ?? 0,
                'subtopics' => $subTopicsStats['completion_percentage'] ?? 0,
                'topic_tests' => ($topicTestsStats['total_tests'] ?? 0) > 0 ? 
                    round((($topicTestsStats['attempted_tests'] ?? 0) / $topicTestsStats['total_tests']) * 100, 2) : 0,
                'subtopic_tests' => ($subTopicTestsStats['total_tests'] ?? 0) > 0 ? 
                    round((($subTopicTestsStats['attempted_tests'] ?? 0) / $subTopicTestsStats['total_tests']) * 100, 2) : 0
            ]
        ];
    }

    /**
     * Get detailed test progress with complete attempt history
     */
    private function getDetailedTestProgress($testId, $userId)
    {
        // Get all attempts for this test
        $allAttempts = CourseResultUser::where([
            'student_id' => $userId,
            'test_id' => $testId
        ])->orderBy('attempt_count')->get();

        if ($allAttempts->isEmpty()) {
            return [
                'total_attempts' => 0,
                'best_score' => 0,
                'latest_score' => 0,
                'is_completed' => false,
                'first_attempted' => null,
                'last_attempted' => null,
                'attempt_history' => []
            ];
        }

        // Get test completion status
        $isCompleted = ManageStudentRecord::where([
            'model_type' => 'App\\Models\\CourseTest',
            'model_id' => $testId,
            'buyer_id' => $userId,
            'is_completed' => config('constants.completed.YES')
        ])->exists();

        // Get total questions for this test
        $totalQuestions = CourseTest::find($testId)->question()->count();

        // Build detailed attempt history
        $attemptHistory = [];
        foreach ($allAttempts as $attempt) {
            // Get answers for this specific attempt
            $attemptAnswers = UserTestAnswer::where([
                'user_id' => $userId,
                'test_id' => $testId,
                'attempt_number' => $attempt->attempt_count
            ])->get();

            $correctAnswers = $attemptAnswers->where('is_correct', true)->count();
            $incorrectAnswers = $attemptAnswers->where('is_correct', false)->count();
            $unanswered = $totalQuestions - $attemptAnswers->count();
            $totalTimeSpent = $attemptAnswers->sum('time_taken_seconds');

            // Get question-wise breakdown
            $questionBreakdown = [];
            foreach ($attemptAnswers as $answer) {
                $question = CourseTest::find($testId)->question()->find($answer->question_id);
                $questionBreakdown[] = [
                    'question_id' => $answer->question_id,
                    'question_text' => $question ? $question->question : 'Question not found',
                    'selected_option_id' => $answer->selected_option_id,
                    'is_correct' => $answer->is_correct,
                    'time_taken_seconds' => $answer->time_taken_seconds,
                    'status' => $answer->is_correct ? 'correct' : 'incorrect'
                ];
            }

            // Calculate improvement from previous attempt
            $improvement = 0;
            if ($attempt->attempt_count > 1) {
                $previousAttempt = $allAttempts->where('attempt_count', $attempt->attempt_count - 1)->first();
                if ($previousAttempt) {
                    $improvement = $attempt->test_score - $previousAttempt->test_score;
                }
            }

            $attemptHistory[] = [
                'attempt_number' => $attempt->attempt_count,
                'score_percentage' => $attempt->test_score,
                'correct_answers' => $correctAnswers,
                'incorrect_answers' => $incorrectAnswers,
                'unanswered' => $unanswered,
                'total_questions' => $totalQuestions,
                'total_time_spent_seconds' => $totalTimeSpent,
                'average_time_per_question' => $attemptAnswers->count() > 0 ? round($totalTimeSpent / $attemptAnswers->count(), 2) : 0,
                'started_at' => $attempt->created_at,
                'completed_at' => $attempt->updated_at,
                'improvement_from_previous' => $improvement,
                'question_breakdown' => $questionBreakdown,
                'performance_summary' => [
                    'accuracy_rate' => $totalQuestions > 0 ? round(($correctAnswers / $totalQuestions) * 100, 2) : 0,
                    'completion_rate' => $totalQuestions > 0 ? round(($attemptAnswers->count() / $totalQuestions) * 100, 2) : 0,
                    'speed_score' => $attemptAnswers->count() > 0 ? round(60 / ($totalTimeSpent / $attemptAnswers->count()), 2) : 0
                ]
            ];
        }

        return [
            'total_attempts' => $allAttempts->count(),
            'best_score' => $allAttempts->max('test_score'),
            'latest_score' => $allAttempts->last()->test_score,
            'average_score' => round($allAttempts->avg('test_score'), 2),
            'is_completed' => $isCompleted,
            'first_attempted' => $allAttempts->first()->created_at,
            'last_attempted' => $allAttempts->last()->updated_at,
            'total_time_spent' => $attemptHistory ? array_sum(array_column($attemptHistory, 'total_time_spent_seconds')) : 0,
            'overall_improvement' => $allAttempts->count() > 1 ? $allAttempts->last()->test_score - $allAttempts->first()->test_score : 0,
            'attempt_history' => $attemptHistory,
            'performance_analytics' => [
                'consistency_score' => $this->calculateConsistencyScore($allAttempts),
                'learning_curve' => $this->calculateLearningCurve($allAttempts),
                'weak_areas' => $this->identifyWeakAreas($testId, $userId),
                'strong_areas' => $this->identifyStrongAreas($testId, $userId)
            ]
        ];
    }

    /**
     * Calculate consistency score based on score variations
     */
    private function calculateConsistencyScore($attempts)
    {
        if ($attempts->count() < 2) return 100;
        
        $scores = $attempts->pluck('test_score')->toArray();
        $mean = array_sum($scores) / count($scores);
        $variance = array_sum(array_map(function($score) use ($mean) {
            return pow($score - $mean, 2);
        }, $scores)) / count($scores);
        
        $standardDeviation = sqrt($variance);
        
        // Convert to consistency score (lower deviation = higher consistency)
        return max(0, 100 - ($standardDeviation * 2));
    }

    /**
     * Calculate learning curve trend
     */
    private function calculateLearningCurve($attempts)
    {
        if ($attempts->count() < 2) return 'insufficient_data';
        
        $scores = $attempts->pluck('test_score')->toArray();
        $firstHalf = array_slice($scores, 0, ceil(count($scores) / 2));
        $secondHalf = array_slice($scores, ceil(count($scores) / 2));
        
        $firstAvg = array_sum($firstHalf) / count($firstHalf);
        $secondAvg = array_sum($secondHalf) / count($secondHalf);
        
        if ($secondAvg > $firstAvg + 5) return 'improving';
        if ($secondAvg < $firstAvg - 5) return 'declining';
        return 'stable';
    }

    /**
     * Identify weak areas (questions frequently answered incorrectly)
     */
    private function identifyWeakAreas($testId, $userId)
    {
        $incorrectAnswers = UserTestAnswer::where([
            'user_id' => $userId,
            'test_id' => $testId,
            'is_correct' => false
        ])->get();

        $questionFrequency = [];
        foreach ($incorrectAnswers as $answer) {
            $questionId = $answer->question_id;
            if (!isset($questionFrequency[$questionId])) {
                $questionFrequency[$questionId] = 0;
            }
            $questionFrequency[$questionId]++;
        }

        arsort($questionFrequency);
        $weakAreas = [];
        
        foreach (array_slice($questionFrequency, 0, 3, true) as $questionId => $frequency) {
            $question = CourseTest::find($testId)->question()->find($questionId);
            $weakAreas[] = [
                'question_id' => $questionId,
                'question_text' => $question ? $question->question : 'Question not found',
                'incorrect_count' => $frequency,
                'difficulty_level' => $frequency > 2 ? 'high' : ($frequency > 1 ? 'medium' : 'low')
            ];
        }

        return $weakAreas;
    }

    /**
     * Identify strong areas (questions frequently answered correctly)
     */
    private function identifyStrongAreas($testId, $userId)
    {
        $correctAnswers = UserTestAnswer::where([
            'user_id' => $userId,
            'test_id' => $testId,
            'is_correct' => true
        ])->get();

        $questionFrequency = [];
        foreach ($correctAnswers as $answer) {
            $questionId = $answer->question_id;
            if (!isset($questionFrequency[$questionId])) {
                $questionFrequency[$questionId] = 0;
            }
            $questionFrequency[$questionId]++;
        }

        arsort($questionFrequency);
        $strongAreas = [];
        
        foreach (array_slice($questionFrequency, 0, 3, true) as $questionId => $frequency) {
            $question = CourseTest::find($testId)->question()->find($questionId);
            $strongAreas[] = [
                'question_id' => $questionId,
                'question_text' => $question ? $question->question : 'Question not found',
                'correct_count' => $frequency,
                'mastery_level' => $frequency > 2 ? 'excellent' : ($frequency > 1 ? 'good' : 'average')
            ];
        }

        return $strongAreas;
    }

    /**
     * Format time remaining in HH:MM:SS format
     */
    private function formatTimeRemaining($endDate)
    {
        $now = Carbon::now();
        $endTime = Carbon::parse($endDate);
        
        // If time has passed, return expired message
        if ($now->greaterThan($endTime)) {
            return [
                'status' => 'expired',
                'message' => 'Time has expired',
                'formatted' => '00:00:00',
                'total_seconds' => 0
            ];
        }
        
        // Calculate difference
        $diff = $now->diff($endTime);
        
        // Convert to total hours, minutes, seconds
        $totalHours = ($diff->days * 24) + $diff->h;
        $minutes = $diff->i;
        $seconds = $diff->s;
        
        // Format as HH:MM:SS
        $formatted = sprintf('%02d:%02d:%02d', $totalHours, $minutes, $seconds);
        
        // Calculate total seconds for frontend countdown
        $totalSeconds = ($diff->days * 24 * 60 * 60) + ($diff->h * 60 * 60) + ($diff->i * 60) + $diff->s;
        
        return [
            'status' => 'active',
            'message' => $totalHours > 24 ? 
                "{$diff->days} days, {$diff->h} hours remaining" : 
                "{$totalHours} hours, {$minutes} minutes remaining",
            'formatted' => $formatted,
            'total_seconds' => $totalSeconds,
            'breakdown' => [
                'days' => $diff->days,
                'hours' => $diff->h,
                'minutes' => $minutes,
                'seconds' => $seconds,
                'total_hours' => $totalHours
            ]
        ];
    }

    public function getHierarchicalData() {
        try {
            $userId = Auth::id();

            // Get courses assigned to the student
            $courses = Course::whereHas(
                'manageStudentRecord',
                function ($q) use ($userId) {
                    return $q->where('buyer_id', $userId);
                }
            )
            ->with([
                'subjects' => function ($query) use ($userId) {
                    $query->with([
                        'courseTopics' => function ($topicQuery) use ($userId) {
                            $topicQuery->whereHas('manageStudentRecord', function ($q) use ($userId) {
                                $q->where('buyer_id', $userId);
                            })
                            ->with([
                                'subtopic' => function ($subtopicQuery) use ($userId) {
                                    $subtopicQuery->whereHas('manageStudentRecord', function ($q) use ($userId) {
                                        $q->where('buyer_id', $userId);
                                    });
                                }
                            ]);
                        }
                    ]);
                }
            ])
            ->get();

            if ($courses->isEmpty()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'No courses found for this student.',
                    'data' => []
                ], 404);
            }

            // Format the response data
            $hierarchicalData = $courses->map(function ($course) {
                return [
                    'course' => [
                        'id' => $course->id,
                        'name' => $course->name
                    ],
                    'subjects' => $course->subjects->map(function ($subject) {
                        return [
                            'id' => $subject->id,
                            'name' => $subject->name,
                            'topics' => $subject->courseTopics->map(function ($topic) {
                                return [
                                    'id' => $topic->id,
                                    'name' => $topic->name,
                                    'subtopics' => $topic->subtopic->map(function ($subtopic) {
                                        return [
                                            'id' => $subtopic->id,
                                            'name' => $subtopic->name
                                        ];
                                    })
                                ];
                            })
                        ];
                    })
                ];
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Hierarchical data fetched successfully.',
                'data' => $hierarchicalData
            ], 200);

        } catch (\Exception $e) {
            return errorLog("Failed to fetch hierarchical data. Message => {$e->getMessage()}, File => {$e->getFile()}, Line No => {$e->getLine()}, Error Code => {$e->getCode()}.");
        }
    }
}
