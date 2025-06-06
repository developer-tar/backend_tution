<?php

namespace App\Console\Commands;

use App\Models\AcdemicCourse;

use App\Models\ManageStudentRecord;
use Illuminate\Console\Command;
use App\Jobs\ProcessStudentCourseRecord;


use App\Models\CourseAssignment;
use App\Models\CourseOption;
use App\Models\CourseQuestion;
use App\Models\CourseSubTopic;
use App\Models\CourseTest;
use App\Models\CourseTopic;

use App\Models\User;
use Illuminate\Support\Facades\Log;

class CourseUploadToUsers extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:course-upload-to-users';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Upload the new course content everyday';

    /**
     * Execute the console command.
     */
    public function handle() {
        try {
            // Fetch all course-student records where parent_id is null
            $details = ManageStudentRecord::select('course_id', 'buyer_id')->whereNull('parent_id')->get();

            // Extract unique student IDs (buyers) and course IDs from the records
            $studentIds = $details->pluck('buyer_id')->unique()->values();
            $courseIds = $details->pluck('course_id')->unique()->values();

            // Get the list of academic courses that match the original course IDs
            $academicCourses = AcdemicCourse::whereIn('course_id', $courseIds)->get(['id', 'course_id']);

            // Get the list of valid user IDs (students) only once
            $userIds = User::whereIn('id', $studentIds)->pluck('id')->toArray();

            // Loop through each academic course
            foreach ($academicCourses as $academicCourse) {
                $academicCourseId = $academicCourse->id;
                $courseId = $academicCourse->course_id;

                // Fetch all assignment IDs for the academic course
                $assignmentIds = CourseAssignment::where('acdemic_course_id', $academicCourseId)->pluck('id')->toArray();

                // Get topic IDs associated with these assignments
                $courseTopicIds = CourseTopic::whereIn('course_assignment_id', $assignmentIds)->pluck('id')->toArray();

                // Get test IDs that are directly under course topics (i.e., not under subtopics)
                $courseTopicTestIds = CourseTest::whereNull('course_sub_topic_id')
                    ->whereIn('course_topic_id', $courseTopicIds)
                    ->pluck('id')
                    ->toArray();

                // Fetch question and option IDs for topic-level tests
                $courseTopicQuestionIds = CourseQuestion::whereIn('course_test_id', $courseTopicTestIds)->pluck('id')->toArray();
                $courseTopicOptionsIds = CourseOption::whereIn('course_question_id', $courseTopicQuestionIds)->pluck('id')->toArray();

                // Get subtopic IDs under the current course topics
                $courseSubTopicIds = CourseSubTopic::whereIn('course_topic_id', $courseTopicIds)->pluck('id')->toArray();

                // Fetch test, question, and option IDs for subtopic-level
                $courseSubTopicTestIds = CourseTest::whereIn('course_sub_topic_id', $courseSubTopicIds)->pluck('id')->toArray();
                $courseSubTopicQuestionIds = CourseQuestion::whereIn('course_test_id', $courseSubTopicTestIds)->pluck('id')->toArray();
                $courseSubTopicOptionsIds = CourseOption::whereIn('course_question_id', $courseSubTopicQuestionIds)->pluck('id')->toArray();

                // Chunk user IDs to avoid memory overload and to support batch queueing
                foreach (array_chunk($userIds, 100) as $userChunk) {
                    foreach ($userChunk as $userId) {
                        // Dispatch the job to process student course data in the background
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
            }
        } catch (\Exception $e) {
            Log::error("Failed to upload new content: {$e->getMessage()} in {$e->getFile()}:{$e->getLine()}");
        }
    }
}
