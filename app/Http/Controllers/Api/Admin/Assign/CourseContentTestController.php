<?php

namespace App\Http\Controllers\Api\Admin\Assign;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\FetchTestList;
use App\Http\Requests\Api\Admin\StoreTestRequest;
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
        try {  // Pull filter vars once for clarity
            $academicCourseId = $request->input('acdemic_course_id');   // keep typo if DB says so
            $subjectId = $request->input('subject_id');
            $assignmentId = $request->input('assignment_id');
            $courseTopicId = $request->input('course_topic_id');
            $courseSubtopicId = $request->input('course_subtopic_id');
            $testId = $request->input('test_id');
            $questionId = $request->input('question_id');
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
                $q->whereHas(
                    'courseTopic.courseAssignment.acdemicCourses',
                    fn($qq) => $qq->where('acdemic_course_id', $academicCourseId)
                );
            })
            ->when($subjectId, fn($q) => $q->whereHas('courseTopic.subject', fn($qq) => $qq->where('id', $subjectId)))
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
            ->paginate(10)->through(function ($item) {

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
}
