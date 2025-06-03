<?php

namespace App\Http\Controllers\Api\Admin\Assign;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\AssignCourseToStudents;
use App\Http\Requests\Api\Admin\FetchAcdemicCourseList;

use App\Http\Requests\Api\Admin\StoreTestRequest;
use App\Jobs\ProcessStudentCourseRecord;
use App\Models\AcdemicCourse;

use App\Models\CourseAssignment;
use App\Models\CourseOption;
use App\Models\CourseQuestion;
use App\Models\CourseSubTopic;
use App\Models\CourseTest;
use App\Models\CourseTopic;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class AssignedStudentCourseController extends Controller {
    /**
     * Store a newly created resource in storage.
     *
     * @param  StoreTestRequest  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function fetchStudentList(FetchAcdemicCourseList $request) {
        try {

            $courseId = AcdemicCourse::where('id', $request->input('acdemic_course_id'))->value('course_id');

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

    public function store(AssignCourseToStudents $request) {

        try {

            DB::beginTransaction();
            $academicCourseId = $request->input('acdemic_course_id');
            $courseId = AcdemicCourse::where('id', $academicCourseId)->value('course_id');

            $assignmentIds = CourseAssignment::where('acdemic_course_id', $academicCourseId)->pluck('id')->toArray();

            $courseTopicIds = CourseTopic::whereIn('course_assignment_id', $assignmentIds)->pluck('id')->toArray();

            $courseTopicTestIds = CourseTest::whereNull('course_sub_topic_id')->whereIn('course_topic_id', $courseTopicIds)->pluck('id')->toArray();
            $courseTopicQuestionIds = CourseQuestion::whereIn('course_test_id', $courseTopicTestIds)->pluck('id')->toArray();
            $courseTopicOptionsIds = CourseOption::whereIn('course_question_id', $courseTopicQuestionIds)->pluck('id')->toArray();

            $courseSubTopicIds = CourseSubTopic::whereIn('course_topic_id', $courseTopicIds)->pluck('id')->toArray();
            $courseSubTopicTestIds = CourseTest::whereIn('course_sub_topic_id', $courseSubTopicIds)->pluck('id')->toArray();
            $courseSubTopicQuestionIds = CourseQuestion::whereIn('course_test_id', $courseSubTopicTestIds)->pluck('id')->toArray();
            $courseSubTopicOptionsIds = CourseOption::whereIn('course_question_id', $courseSubTopicQuestionIds)->pluck('id')->toArray();

            $userIds = User::whereIn('id', $request->input('student_id'))->pluck('id')->toArray();

            foreach (array_chunk($userIds, 100) as $userChunk) {
                foreach ($userChunk as $userId) {
                    ProcessStudentCourseRecord::dispatch(
                        $courseId,
                        $userId,
                        $assignmentIds,
                        $courseTopicIds,
                        $courseTopicTestIds,
                        $courseTopicQuestionIds,
                        $courseTopicOptionsIds,
                        $courseSubTopicIds,
                        $courseSubTopicTestIds,
                        $courseSubTopicQuestionIds,
                        $courseSubTopicOptionsIds
                    );
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Course has been assigned successfully",
            ], 200);
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
