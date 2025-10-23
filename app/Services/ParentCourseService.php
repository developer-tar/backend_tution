<?php

namespace App\Services;

use App\Jobs\ProcessStudentCourseRecord;
use App\Models\AcdemicCourse;
use App\Models\CourseAssignment;
use App\Models\CourseOption;
use App\Models\CoursePrice;
use App\Models\CourseQuestion;
use App\Models\CourseSubTopic;
use App\Models\CourseTest;
use App\Models\CourseTopic;
use App\Models\ManageStudentRecord;
use App\Models\StudentDetail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ParentCourseService
{
    /**
     * Get parent's active subscriptions
     */
    public function getParentSubscriptions($parentId)
    {
        $parent = User::find($parentId);
        
        if (!$parent) {
            return [];
        }

        return $parent->subscriptions()
            ->where('stripe_status', config('constants.active_status'))
            ->whereNotNull('stripe_price')
            ->pluck('stripe_price')
            ->toArray();
    }

    /**
     * Get available courses based on parent's subscriptions
     */
    public function getAvailableCourses($subscriptionPrices)
    {
        return CoursePrice::with(['course:id,name'])
            ->whereIn('stripe_price_id', $subscriptionPrices)
            ->get()
            ->map(function ($coursePrice) {
                return [
                    'course_id' => $coursePrice->course_id,
                    'course_name' => $coursePrice->course->name,
                    'stripe_price_id' => $coursePrice->stripe_price_id,
                    'amount' => $coursePrice->amount,
                    'currency' => $coursePrice->currency,
                ];
            })
            ->unique('course_id')
            ->values();
    }

    /**
     * Get parent's students with course assignment info
     */
    public function getStudentsWithCourseInfo($parentId, $courseIds)
    {
        return StudentDetail::with(['student:id,first_name,last_name,email'])
            ->where('parent_id', $parentId)
            ->get()
            ->map(function ($studentDetail) use ($courseIds) {
                $studentId = $studentDetail->student->id;
                
                // Get already assigned courses for this student
                $assignedCourseIds = ManageStudentRecord::where('buyer_id', $studentId)
                    ->whereIn('course_id', $courseIds)
                    ->pluck('course_id')
                    ->toArray();

                // Get available courses (not yet assigned)
                $availableForStudent = array_diff($courseIds, $assignedCourseIds);

                return [
                    'student_id' => $studentId,
                    'student_name' => $studentDetail->student->first_name . ' ' . $studentDetail->student->last_name,
                    'student_email' => $studentDetail->student->email,
                    'display_name' => $studentDetail->display_name,
                    'assigned_courses_count' => count($assignedCourseIds),
                    'available_courses_count' => count($availableForStudent),
                    'available_course_ids' => array_values($availableForStudent),
                ];
            })
            ->filter(function ($student) {
                // Only show students who have available courses to assign
                return $student['available_courses_count'] > 0;
            })
            ->values();
    }

    /**
     * Assign course to student with complete course structure (like Admin)
     */
    public function assignCourseToStudent($parentId, $studentId, $courseId)
    {
        DB::beginTransaction();
        
        try {
            // Get academic course ID for this course
            $academicCourse = AcdemicCourse::where('course_id', $courseId)->first();
            
            if (!$academicCourse) {
                return [
                    'success' => false,
                    'message' => 'Academic course not found for this course.',
                    'status_code' => 404
                ];
            }

            $academicCourseId = $academicCourse->id;

            // Get all course components (same as Admin logic)
            $assignmentIds = CourseAssignment::where('acdemic_course_id', $academicCourseId)->pluck('id')->toArray();

            $courseTopicIds = CourseTopic::whereIn('course_assignment_id', $assignmentIds)->pluck('id')->toArray();

            $courseTopicTestIds = CourseTest::whereNull('course_sub_topic_id')->whereIn('course_topic_id', $courseTopicIds)->pluck('id')->toArray();
            $courseTopicQuestionIds = CourseQuestion::whereIn('course_test_id', $courseTopicTestIds)->pluck('id')->toArray();
            $courseTopicOptionsIds = CourseOption::whereIn('course_question_id', $courseTopicQuestionIds)->pluck('id')->toArray();

            $courseSubTopicIds = CourseSubTopic::whereIn('course_topic_id', $courseTopicIds)->pluck('id')->toArray();
            $courseSubTopicTestIds = CourseTest::whereIn('course_sub_topic_id', $courseSubTopicIds)->pluck('id')->toArray();
            $courseSubTopicQuestionIds = CourseQuestion::whereIn('course_test_id', $courseSubTopicTestIds)->pluck('id')->toArray();
            $courseSubTopicOptionsIds = CourseOption::whereIn('course_question_id', $courseSubTopicQuestionIds)->pluck('id')->toArray();

            // Dispatch background job for complete course structure processing
            ProcessStudentCourseRecord::dispatch(
                $courseId,
                $studentId,
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

            DB::commit();

            Log::info("Course assigned successfully with complete structure. Parent ID: {$parentId}, Student ID: {$studentId}, Course ID: {$courseId}");

            return [
                'success' => true,
                'message' => 'Course assigned to student successfully with complete structure.',
                'status_code' => 200
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to assign course to student. Message => {$e->getMessage()}, File => {$e->getFile()}, Line => {$e->getLine()}, Code => {$e->getCode()}.");
            throw $e;
        }
    }

    /**
     * Assign course to multiple students (bulk assignment)
     */
    public function assignCourseToMultipleStudents($parentId, $studentIds, $courseId)
    {
        DB::beginTransaction();
        
        try {
            // Get academic course ID for this course
            $academicCourse = AcdemicCourse::where('course_id', $courseId)->first();
            
            if (!$academicCourse) {
                return [
                    'success' => false,
                    'message' => 'Academic course not found for this course.',
                    'status_code' => 404
                ];
            }

            $academicCourseId = $academicCourse->id;

            // Get all course components (same as Admin logic)
            $assignmentIds = CourseAssignment::where('acdemic_course_id', $academicCourseId)->pluck('id')->toArray();

            $courseTopicIds = CourseTopic::whereIn('course_assignment_id', $assignmentIds)->pluck('id')->toArray();

            $courseTopicTestIds = CourseTest::whereNull('course_sub_topic_id')->whereIn('course_topic_id', $courseTopicIds)->pluck('id')->toArray();
            $courseTopicQuestionIds = CourseQuestion::whereIn('course_test_id', $courseTopicTestIds)->pluck('id')->toArray();
            $courseTopicOptionsIds = CourseOption::whereIn('course_question_id', $courseTopicQuestionIds)->pluck('id')->toArray();

            $courseSubTopicIds = CourseSubTopic::whereIn('course_topic_id', $courseTopicIds)->pluck('id')->toArray();
            $courseSubTopicTestIds = CourseTest::whereIn('course_sub_topic_id', $courseSubTopicIds)->pluck('id')->toArray();
            $courseSubTopicQuestionIds = CourseQuestion::whereIn('course_test_id', $courseSubTopicTestIds)->pluck('id')->toArray();
            $courseSubTopicOptionsIds = CourseOption::whereIn('course_question_id', $courseSubTopicQuestionIds)->pluck('id')->toArray();

            $userIds = User::whereIn('id', $studentIds)->pluck('id')->toArray();

            // Process in chunks like Admin (100 students per chunk)
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

            Log::info("Course assigned successfully to multiple students. Parent ID: {$parentId}, Students: " . implode(',', $studentIds) . ", Course ID: {$courseId}");

            return [
                'success' => true,
                'message' => 'Course assigned to students successfully with complete structure.',
                'status_code' => 200
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to assign course to multiple students. Message => {$e->getMessage()}, File => {$e->getFile()}, Line => {$e->getLine()}, Code => {$e->getCode()}.");
            throw $e;
        }
    }

    /**
     * Verify student belongs to parent
     */
    public function verifyStudentBelongsToParent($parentId, $studentId)
    {
        return StudentDetail::where('parent_id', $parentId)
            ->where('child_id', $studentId)
            ->exists();
    }

    /**
     * Check if parent has subscription for course
     */
    public function parentHasSubscriptionForCourse($parentId, $courseId)
    {
        $parentSubscriptions = $this->getParentSubscriptions($parentId);

        if (empty($parentSubscriptions)) {
            return false;
        }

        return CoursePrice::where('course_id', $courseId)
            ->whereIn('stripe_price_id', $parentSubscriptions)
            ->exists();
    }
}
