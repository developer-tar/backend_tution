<?php

namespace App\Http\Controllers\Api\Parent;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Parent\AssignCourseToStudentRequest;
use App\Http\Requests\Api\Parent\FetchParentStudentsRequest;
use App\Services\ParentCourseService;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ParentStudentCourseController extends Controller
{
    protected $parentCourseService;
    protected $subscriptionService;

    public function __construct(ParentCourseService $parentCourseService, SubscriptionService $subscriptionService)
    {
        $this->parentCourseService = $parentCourseService;
        $this->subscriptionService = $subscriptionService;
    }

    /**
     * Fetch students belonging to parent and available courses based on parent's subscriptions
     */
    public function fetchStudentsWithAvailableCourses(FetchParentStudentsRequest $request)
    {
        try {
            $parentId = auth()->user()->id;
          
            // Get available courses (subscriptions and students already validated in FormRequest)
            $availableCourses = $request->input('available_courses');
           
            $courseIds = collect($availableCourses)->pluck('course_id')->toArray();
            
            // Get parent's students with course assignment info
            $students = $this->parentCourseService->getStudentsWithCourseInfo($parentId, $courseIds);

            return response()->json([
                'success' => true,
                'data' => [
                    'students' => $students,
                    'available_courses' => $availableCourses,
                ],
                'message' => $students->isNotEmpty() 
                    ? 'Students and available courses fetched successfully.' 
                    : 'All students have been assigned available courses.',
            ], 200);

        } catch (\Exception $e) {
            return errorLog("Failed to fetch students with available courses. Message => {$e->getMessage()}, File => {$e->getFile()}, Line => {$e->getLine()}, Code => {$e->getCode()}.");
        }
    }

    /**
     * Assign course to student(s) - handles both single and multiple students
     */
    public function assignCourseToStudent(AssignCourseToStudentRequest $request)
    {
        try {
            $parentId = auth()->user()->id;
            $courseId = $request->input('course_id');
            $studentIds = $request->input('student_ids');

            // Check if single or multiple students
            if (count($studentIds) === 1) {
                // Single student assignment
                $result = $this->parentCourseService->assignCourseToStudent($parentId, $studentIds[0], $courseId);
            } else {
                // Multiple students assignment
                $result = $this->parentCourseService->assignCourseToMultipleStudents($parentId, $studentIds, $courseId);
            }

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
            ], $result['status_code']);

        } catch (\Exception $e) {
            return errorLog("Failed to assign course to student(s). Message => {$e->getMessage()}, File => {$e->getFile()}, Line => {$e->getLine()}, Code => {$e->getCode()}.");
        }
    }

    /**
     * Get parent subscriptions.
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSubscriptions()
    {
        try {
            $parentId = auth()->user()->id;
            $subscriptions = $this->subscriptionService->getSubscriptionsForParent($parentId);

            return response()->json([
                'success' => true,
                'data' => $subscriptions,
                'message' => 'Subscriptions fetched successfully.',
            ], 200);

        } catch (\Exception $e) {
            Log::error("Failed to fetch subscriptions. Message => {$e->getMessage()}");
            return sendError('error', ['error' => 'An error occurred while fetching subscriptions.'], 500);
        }
    }
}
