<?php

namespace App\Http\Controllers\Api\Admin\Assign;

use App\Http\Controllers\Controller;
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

class CourseContentTestController extends Controller {
    /**
     * Store a newly created resource in storage.
     *
     * @param  StoreTestRequest  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(StoreTestRequest $request) {
       
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
                    'course_sub_topic_id' => $request->filled('subtopic_id') ? $request->input('subtopic_id'): null,
                    'name' =>  $name . "-Test ",
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
    public function fetchTopic($subject_id, $course_assignment_id) {
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
    public function fetchSubTopic($topic_id) {
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
