<?php

namespace App\Http\Controllers\Api\Admin\Assign;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\AssignCourseToStudents;
use App\Http\Requests\Api\Admin\FetchAcdemicCourseList;

use App\Http\Requests\Api\Admin\StoreTestRequest;

use App\Models\AcdemicCourse;
use App\Models\CourseAnswer;
use App\Models\CourseAssignment;
use App\Models\CourseOption;
use App\Models\CourseQuestion;
use App\Models\CourseSubTopic;
use App\Models\CourseTest;
use App\Models\CourseTopic;

use App\Models\CourseUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AssignedStudentCourseController extends Controller
{
    /**
     * Store a newly created resource in storage.
     *
     * @param  StoreTestRequest  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function fetchStudentList(FetchAcdemicCourseList $request)
    {
        try {
            $courseId = AcdemicCourse::value('course_id', $request->input('acdemic_course_id'));

            $users = User::with('roles', 'course')
                ->whereDoesntHave('course', fn($q) => $q->where('courses.id', $courseId))
                ->whereHas('roles', fn($q) => $q->where('name', config('constants.roles.STUDENT')))
                ->get()->transform(function ($item) {
                    return [
                        'id' => $item->id,
                        'name' => $item->full_name,
                    ];
                });
            $response = [
                'success' => true,
                'data' => $users,
                'message' => $users->isNotEmpty() ? 'Student list fetched successfully.' : 'No students found.',
            ];
            return response()->json($response, 200);
        } catch (\Exception $e) {
            Log::error("Failed to fetch student list: {$e->getMessage()} in {$e->getFile()}:{$e->getLine()}");
            $response = [
                'success' => false,
                'message' => 'An error occurred while fetching student list.',

            ];
            return response()->json($response, 500);
        }


    }

    public function store(AssignCourseToStudents $request)
    {

        try {
            DB::beginTransaction();
            $courseId = AcdemicCourse::value('course_id', $request->input('acdemic_course_id'));//get the course id 

            $assignmentIds = CourseAssignment::where('acdemic_course_id', $request->input('acdemic_course_id'))->pluck('id'); //get the course assign assignment ids 

            $now = now();  // Get current timestamp once

            // $pivotDataForAssignment = [];

            // if ($assignmentIds->isNotEmpty()) {
            //     foreach ($assignmentIds as $assignmentId) {
            //         $pivotDataForAssignment[$assignmentId] = [
            //             'course_id' => $courseId,
            //             'created_at' => $now,
            //             'updated_at' => $now,
            //         ];
            //     }
            // }

            $users = User::whereIn('id', $request->student_id)->get();

            foreach ($users as $user) {
                 $user->course()->syncWithoutDetaching([ 
                    $courseId => [
                        'created_at' => $now,
                        'updated_at' => $now,
                        'status' => config('constants.statuses.APPROVED')
                    ]
                ]);
            }

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
}
