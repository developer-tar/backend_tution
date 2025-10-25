<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Student\SubTopicTestIdRequest;
use App\Http\Requests\Api\Student\SubTopicTestSubmissionRequest;
use App\Http\Requests\Api\Student\TestIdRequest;
use App\Http\Requests\Api\Student\TopicTestSubmissionRequest;
use App\Services\TestSubmissionService;
use App\Services\TestAccessService;
use App\Services\AssignmentService;
use Illuminate\Support\Facades\Auth;

class AssignmentController extends Controller 
{
    protected $testSubmissionService;
    protected $testAccessService;
    protected $assignmentService;

    public function __construct(
        TestSubmissionService $testSubmissionService,
        TestAccessService $testAccessService,
        AssignmentService $assignmentService
    ) {
        $this->testSubmissionService = $testSubmissionService;
        $this->testAccessService = $testAccessService;
        $this->assignmentService = $assignmentService;
    }

    /**
     * Get topic test data
     */
    public function topicTest(TestIdRequest $request) 
    {
        try {
            $testData = $this->testAccessService->getTestData(
                $request->input('test_id'), 
                'topic'
            );

            return response()->json([
                'success' => true,
                'message' => 'Test data fetched successfully.',
                'data' => $testData,
            ]);
        } catch (\Exception $e) {
            \Log::error("Failed to fetch the topic test. Message => {$e->getMessage()}, File => {$e->getFile()}, Line No => {$e->getLine()}, Error Code => {$e->getCode()}.");

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [],
            ], $e->getMessage() === 'Test not found.' ? 404 : 403);
        }
    }

    /**
     * Submit topic test
     */
    public function submitTopicTest(TopicTestSubmissionRequest $request) 
    {
        try {
            $results = $this->testSubmissionService->submitTest(
                Auth::id(),
                $request->input('test_id'),
                $request->input('answers'),
                'topic'
            );

            $message = $results['is_completed'] 
                ? 'Test completed successfully!' 
                : 'Test submitted. You can retry to get all answers correct.';

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $results,
            ], 200);

        } catch (\Exception $e) {
            \Log::error("Failed to submit topic test. Message => {$e->getMessage()}, File => {$e->getFile()}, Line No => {$e->getLine()}, Error Code => {$e->getCode()}.");

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    /**
     * Get sub-topic test data
     */
    public function subTopicTest(SubTopicTestIdRequest $request) 
    {
        try {
            $testData = $this->testAccessService->getTestData(
                $request->input('sub_topic_test_id'), 
                'subtopic'
            );

            return response()->json([
                'success' => true,
                'message' => 'Sub topic test data fetched successfully.',
                'data' => $testData,
            ]);
        } catch (\Exception $e) {
            \Log::error("Failed to fetch the sub topic test. Message => {$e->getMessage()}, File => {$e->getFile()}, Line No => {$e->getLine()}, Error Code => {$e->getCode()}.");

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [],
            ], $e->getMessage() === 'Test not found.' ? 404 : 403);
        }
    }

    /**
     * Submit sub-topic test
     */
    public function submitSubTopicTest(SubTopicTestSubmissionRequest $request) 
    {
        try {
            $results = $this->testSubmissionService->submitTest(
                Auth::id(),
                $request->input('sub_topic_test_id'),
                $request->input('answers'),
                'subtopic'
            );

            $message = $results['is_completed'] 
                ? 'Sub topic test completed successfully!' 
                : 'Sub topic test submitted. You can retry to get all answers correct.';

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $results,
            ], 200);

        } catch (\Exception $e) {
            \Log::error("Failed to submit sub topic test. Message => {$e->getMessage()}, File => {$e->getFile()}, Line No => {$e->getLine()}, Error Code => {$e->getCode()}.");

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    /**
     * Fetch subjects for current user
     */
    public function fetchSubjects() 
    {
        try {
            $subjects = $this->assignmentService->fetchSubjects();

            return response()->json([
                'success' => true,
                'data' => $subjects,
                'message' => 'Subjects Fetched Successfully!!',
            ], 200);
        } catch (\Exception $e) {
            \Log::error("Failed to fetch subjects. Message => {$e->getMessage()}, File => {$e->getFile()}, Line No => {$e->getLine()}, Error Code => {$e->getCode()}.");

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [],
            ], 500);
        }
    }
}
