<?php

namespace App\Http\Controllers\Api\Admin\Assign;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\FetchTestList;
use App\Http\Requests\Api\Admin\StoreTestRequest;
use App\Http\Requests\Api\Admin\UpdateTestRequest;
use App\Http\Requests\Api\Admin\StoreTopicSubTopicRequest;
use App\Models\CourseAnswer;
use App\Models\CourseOption;
use App\Models\CourseQuestion;
use App\Models\CourseSubTopic;
use App\Models\CourseTest;
use App\Models\CourseTopic;


use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CourseContentTestController extends Controller
{
    /**
     * Store a newly created resource in storage.
     *
     * @param  StoreTestRequest  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function index(FetchTestList $request)
    {
        try { 
            // Pull filter vars once for clarity
            $academicCourseId = $request->input('academic_course_id') ?: $request->input('acdemic_course_id');   // handle both spellings
            $subjectId = $request->input('subject_id');
            $assignmentId = $request->input('assignment_id');
            $courseTopicId = $request->input('course_topic_id');
            $courseSubtopicId = $request->input('course_subtopic_id');
            $testId = $request->input('test_id');
            $questionId = $request->input('question_id');
            
            // Debug logging
            Log::info("Test API Filters: academic_course_id={$academicCourseId}, subject_id={$subjectId}, assignment_id={$assignmentId}");
            
            // Check if any filters are provided (only check main filter keys)
            $hasFilters = !empty($academicCourseId) || !empty($subjectId) || !empty($assignmentId) || !empty($courseTopicId) || !empty($courseSubtopicId);

            $tests = CourseTest::with([
                'courseTopic:id,name,subject_id,course_assignment_id',
                'courseTopic.subject:id,name',
                'courseTopic.courseAssignment:id,week_id,acdemic_course_id',
                'courseTopic.courseAssignment.weeks:id,week_number,start_date,end_date',
                'courseTopic.courseAssignment.acdemicCourses:id,course_id,acdemic_id',
                'courseTopic.courseAssignment.acdemicCourses.courses:id,name',
                'courseTopic.courseAssignment.acdemicCourses.acdemicyears',
                'courseSubTopic:id,name,course_topic_id',
                'question:id,course_test_id,name,duration_in_sec',
                'question.options:id,course_question_id,name',
                'question.options.answer:id,course_option_id',
            ])
            ->when($academicCourseId, function ($q) use ($academicCourseId) {
                $q->whereHas('courseTopic.courseAssignment', function ($qq) use ($academicCourseId) {
                    $qq->where('acdemic_course_id', $academicCourseId);
                });
            })
            ->when($subjectId, function ($q) use ($subjectId) {
               
                $q->whereHas('courseTopic', function ($qq) use ($subjectId) {
                    $qq->where('subject_id', $subjectId);
                });
            })
            ->when($assignmentId, function ($q) use ($assignmentId) {
                $q->whereHas(
                    'courseTopic.courseAssignment',
                    fn($qq) => $qq->where('id', $assignmentId)
                );
            })  
            ->when($courseTopicId, fn($q) => $q->where('course_topic_id', $courseTopicId))
            ->when($courseSubtopicId, function ($q) use ($courseSubtopicId) {
                $q->whereHas(
                    'courseSubTopic',
                    fn($qq) => $qq->where('id', $courseSubtopicId)
                );
            })
            ->when($testId, fn($q) => $q->where('id', $testId))
            ->when($questionId, function ($q) use ($questionId) {
                $q->whereHas(
                    'question',
                    fn($qq) => $qq->where('id', $questionId)
                );
            })
            ->orderBy('created_at', 'desc')
            ->paginate($hasFilters ? 10 : 50)->through(function ($item) {

                return [
                    'test_id' => $item->id,
                    'test_name' => $item->name,
                    'course_name' => optional($item->courseTopic?->courseAssignment?->acdemicCourses?->courses)->name,
                    'topic_name' => optional($item->courseTopic)->name,
                    'subtopic_name' => optional($item->courseSubTopic)->name,
                    'subject_name' => optional($item->courseTopic?->subject)->name,
                    'questions' => $item->question->map(function ($question) {
                        // Extract correct answer (first matching option)
                        $correctOption = $question->options->first(function ($option) {
                            return optional($option->answer->first())->course_option_id === $option->id;
                        });
                        return [
                            'id' => $question->id,
                            'name' => $question->name,
                            'duration_in_sec' => $question->duration_in_sec,
                            'correct_answer' => $correctOption?->name,
                            'options' => $question->options->map(function ($option) {
                                return [
                                    'id' => $option->id,
                                    'name' => $option->name,
                                    
                                ];
                            }),
                        ];
                    }),
                ];
            });
           
            $response = [
                'success' => true,
                'message' => $tests->total() ? 'Courses Test fetched successfully.'
                    : 'No record found.',
                'data' => $tests,
            ];

            return response()->json($response, 200);

        } catch (\Throwable $e) {
            Log::error("Failed to fetch topics: {$e->getMessage()} in {$e->getFile()}:{$e->getLine()}");
            return response()->json(
                ['message' => 'An error occurred while fetching topics and subtopics.'],
                500
            );
        }


    }
    public function store(StoreTestRequest $request)
    {

        try {
            DB::beginTransaction();

            $query = CourseTopic::select('name')->findOrFail($request->input('topic_id'));
            $name = $query['name'];
            if ($request->filled('subtopic_id')) {
                $query = CourseSubTopic::select('name')->findOrFail($request->input('subtopic_id'));
                $name = $query['name'];
            }

            $testObj = CourseTest::firstOrCreate(
                [
                    'course_topic_id' => $request->input('topic_id'),
                    'course_sub_topic_id' => $request->filled('subtopic_id') ? $request->input('subtopic_id') : null,
                    'name' => $name . "-Test ",
                ]
            );
            foreach ($request->input('questions') as $key => $question) {
                $questionObj = CourseQuestion::firstOrCreate(
                    [
                        'course_test_id' => $testObj?->id,
                        'name' => $question,
                        'duration_in_sec' => $request->input('duration_in_sec')[$key]
                    ]
                );
                foreach ($request->input('options')[$key] as $option) {
                    $optionObj = CourseOption::firstOrCreate(
                        [
                            'course_question_id' => $questionObj?->id,
                            'name' => $option,
                        ]
                    );
                    if ($optionObj?->name == $request->input('answers')[$key])
                        CourseAnswer::firstOrCreate(
                            [
                                'course_option_id' => $optionObj?->id,
                            ]
                        );
                }
            }

            DB::commit();

            $response = [
                'success' => true,
                'message' => "Test has created successfully",
            ];
            return response()->json($response, 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to create test for course. Message => {$e->getMessage()}, File => {$e->getFile()}, Line => {$e->getLine()}, Code => {$e->getCode()}.");
            $response = [
                'success' => false,
                'message' => "An error occurred during store",
            ];
            return response()->json($response, 500);
        }
    }
    public function fetchTopic($subject_id, $course_assignment_id)
    {
        try {
            $data = CourseTopic::select('id', 'name')->where(['subject_id' => $subject_id, 'course_assignment_id' => $course_assignment_id])->get();
            if ($data->isNotEmpty()) {
                $response = [
                    'success' => true,
                    "data" => $data,
                    'message' => "Fetched successfully",
                ];
            } else {
                $response = [
                    'success' => true,
                    'message' => "No Course Topic found",
                ];
            }

            return response()->json($response, 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to fetch the course. Message => {$e->getMessage()}, File => {$e->getFile()}, Line => {$e->getLine()}, Code => {$e->getCode()}.");
            $response = [
                'success' => false,
                'message' => "An error occurred during fetch",
            ];
            return response()->json($response, 500);
        }
    }
    public function fetchSubTopic($topic_id)
    {
        try {
            $data = CourseSubTopic::select('id', 'name')->where('course_topic_id', $topic_id)->get();
            if ($data->isNotEmpty()) {
                $response = [
                    'success' => true,
                    "data" => $data,
                    'message' => "Fetched successfully",
                ];
            } else {
                $response = [
                    'success' => true,
                    'message' => "No Course SubTopic found",
                ];
            }

            return response()->json($response, 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to fetch subtopic. Message => {$e->getMessage()}, File => {$e->getFile()}, Line => {$e->getLine()}, Code => {$e->getCode()}.");
            $response = [
                'success' => false,
                'message' => "An error occurred during fetch",
            ];
            return response()->json($response, 500);
        }
    }

    /**
     * Display the specified test.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $test = CourseTest::with([
                'courseTopic:id,name,subject_id,course_assignment_id',
                'courseTopic.subject:id,name',
                'courseSubTopic:id,name,course_topic_id',
                'question:id,course_test_id,name,duration_in_sec',
                'question.options:id,course_question_id,name',
                'question.options.answer:id,course_option_id',
            ])->findOrFail($id);

            // Format questions with options and correct answer for editing
            $questions = [];
            $options = [];
            $answers = [];
            $duration_in_sec = [];

            foreach ($test->question as $question) {
                $questionOptions = $question->options->pluck('name')->toArray();
                $options[] = $questionOptions;
                
                // Find correct answer
                $correctAnswer = null;
                foreach ($question->options as $option) {
                    if ($option->answer->isNotEmpty()) {
                        $correctAnswer = $option->name;
                        break;
                    }
                }
                
                $questions[] = $question->name;
                $answers[] = $correctAnswer;
                $duration_in_sec[] = $question->duration_in_sec;
            }

            $data = [
                'id' => $test->id,
                'test_name' => $test->name,
                'topic_id' => $test->course_topic_id,
                'subtopic_id' => $test->course_sub_topic_id,
                'topic_name' => $test->courseTopic->name ?? null,
                'subtopic_name' => $test->courseSubTopic->name ?? null,
                'subject_name' => $test->courseTopic->subject->name ?? null,
                'questions' => $questions,
                'options' => $options,
                'answers' => $answers,
                'duration_in_sec' => $duration_in_sec,
            ];

            return response()->json([
                'success' => true,
                'message' => 'Test fetched successfully.',
                'data' => $data,
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return sendError('error', ['error' => 'Test not found.'], 404);
        } catch (\Exception $e) {
            Log::error("Failed to fetch test. Message => {$e->getMessage()}, File => {$e->getFile()}, Line => {$e->getLine()}, Code => {$e->getCode()}.");
            return sendError('error', ['error' => 'An error occurred while fetching test.'], 500);
        }
    }

    /**
     * Update the specified test.
     *
     * @param  UpdateTestRequest  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdateTestRequest $request, $id)
    {
        try {
            DB::beginTransaction();

            $testObj = CourseTest::findOrFail($id);

            // Update test basic fields
            if ($request->has('topic_id')) {
                $testObj->course_topic_id = $request->topic_id;
            }
            if ($request->has('subtopic_id')) {
                $testObj->course_sub_topic_id = $request->subtopic_id;
            }

            // Update test name if topic/subtopic changed
            if ($request->has('topic_id') || $request->has('subtopic_id')) {
                $query = CourseTopic::select('name')->findOrFail($testObj->course_topic_id);
                $name = $query['name'];
                if ($testObj->course_sub_topic_id) {
                    $query = CourseSubTopic::select('name')->findOrFail($testObj->course_sub_topic_id);
                    $name = $query['name'];
                }
                $testObj->name = $name . "-Test ";
            }

            $testObj->save();

            // Update questions, options, and answers if provided
            if ($request->has('questions') && $request->has('options') && $request->has('answers') && $request->has('duration_in_sec')) {
                // Delete existing questions (cascade will handle options and answers)
                $testObj->question()->delete();

                // Create new questions, options, and answers
                foreach ($request->input('questions') as $key => $question) {
                    $questionObj = CourseQuestion::create([
                        'course_test_id' => $testObj->id,
                        'name' => $question,
                        'duration_in_sec' => $request->input('duration_in_sec')[$key]
                    ]);

                    foreach ($request->input('options')[$key] as $option) {
                        $optionObj = CourseOption::create([
                            'course_question_id' => $questionObj->id,
                            'name' => $option,
                        ]);

                        // Mark correct answer
                        if ($optionObj->name == $request->input('answers')[$key]) {
                            CourseAnswer::create([
                                'course_option_id' => $optionObj->id,
                            ]);
                        }
                    }
                }
            }

            DB::commit();

            $response = [
                'success' => true,
                'message' => 'Test updated successfully.',
            ];
            return response()->json($response, 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return sendError('error', ['error' => 'Test not found.'], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to update test. Message => {$e->getMessage()}, File => {$e->getFile()}, Line => {$e->getLine()}, Code => {$e->getCode()}.");
            $response = [
                'success' => false,
                'message' => "An error occurred during update",
            ];
            return response()->json($response, 500);
        }
    }
}
