<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\WeeklyPerformanceRequest;
use App\Models\Course;
use App\Models\CourseAssignment;
use App\Models\CourseTest;
use App\Models\ManageStudentRecord;
use App\Models\UserTestAnswer;
use App\Models\Subject;
use Carbon\Carbon;

class WeeklyPerformanceController extends Controller
{
    /**
     * Get comprehensive weekly performance data for a specific subject
     * Shows total counts, completed counts, and current week information
     */
    public function getWeeklyPerformance(WeeklyPerformanceRequest $request)
    {
        try {
            $userId = auth()->id();
            $subjectId = $request->subject_id;
            $currentDate = Carbon::now();
            
            \Log::info('=== FLOW: Weekly Performance Request (WeeklyPerformance) ===', [
                'user_id' => $userId,
                'subject_id' => $subjectId,
                'current_date' => $currentDate->toDateString(),
                'timestamp' => $currentDate->toDateTimeString()
            ]);

            // Get subject details
            $subject = Subject::find($subjectId);
            if (!$subject) {
                return response()->json([
                    'success' => false,
                    'message' => 'Subject not found.',
                    'data' => []
                ], 404);
            }

            // Step 1: Get user's courses for this subject
            $courses = Course::whereHas(
                'manageStudentRecord',
                fn($q) => $q->where('buyer_id', $userId)
            )
            ->whereHas('subjects', fn($q) => $q->where('subjects.id', $subjectId))
            ->with(['manageStudentRecord:id,model_id,model_type'])
            ->get();

            if ($courses->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'No courses found for this subject.',
                    'data' => [
                        'subject_info' => [
                            'id' => $subject->id,
                            'name' => $subject->name
                        ],
                        'current_week_info' => null,
                        'progress_summary' => [
                            'total_topics' => 0,
                            'completed_topics' => 0,
                            'total_subtopics' => 0,
                            'completed_subtopics' => 0,
                            'total_topic_tests' => 0,
                            'completed_topic_tests' => 0,
                            'total_subtopic_tests' => 0,
                            'completed_subtopic_tests' => 0
                        ]
                    ]
                ], 200);
            }

            $courseIds = $courses->flatMap(fn($course) => $course->manageStudentRecord->pluck('id'))->values();
            
            \Log::info('=== FLOW: Courses Found (WeeklyPerformance) ===', [
                'courses_count' => $courses->count(),
                'actual_course_ids' => $courses->pluck('id')->toArray(),
                'course_msr_ids' => $courseIds->toArray(),
                'subject_id' => $subjectId,
                'subject_name' => $subject->name
            ]);

            // Step 2: Get all assignments (past, current, future)
            $assignments = CourseAssignment::with(['weeks', 'manageStudentRecord', 'acdemicCourses.courses'])
                ->whereHas('manageStudentRecord', fn($q) => $q->whereIn('parent_id', $courseIds))
                ->orderBy('id')
                ->get();

            if ($assignments->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'No assignments found.',
                    'data' => [
                        'subject_info' => [
                            'id' => $subject->id,
                            'name' => $subject->name
                        ],
                        'current_week_info' => null,
                        'progress_summary' => [
                            'total_topics' => 0,
                            'completed_topics' => 0,
                            'total_subtopics' => 0,
                            'completed_subtopics' => 0,
                            'total_topic_tests' => 0,
                            'completed_topic_tests' => 0,
                            'total_subtopic_tests' => 0,
                            'completed_subtopic_tests' => 0
                        ]
                    ]
                ], 200);
            }
            
            // Step 3: Find current week assignment
            $currentWeekAssignment = null;
            foreach ($assignments as $assignment) {
                if (!$assignment->weeks) continue;
                
                $weekStart = Carbon::parse($assignment->weeks->start_date);
                $weekEnd = Carbon::parse($assignment->weeks->end_date);
                
                if ($currentDate->between($weekStart, $weekEnd)) {
                    $currentWeekAssignment = $assignment;
                    break;
                }
            }

            \Log::info('=== FLOW: Assignments Found (WeeklyPerformance) ===', [
                'assignments_count' => $assignments->count(),
                'current_week_assignment' => $currentWeekAssignment ? [
                    'assignment_id' => $currentWeekAssignment->id,
                    'week_number' => $currentWeekAssignment->weeks->week_number,
                    'week_start_date' => $currentWeekAssignment->weeks->start_date,
                    'week_end_date' => $currentWeekAssignment->weeks->end_date
                ] : null,
                'current_date' => $currentDate->toDateString()
            ]);

            // Step 4: Calculate comprehensive progress using MSR approach
            $progressData = $this->calculateSubjectProgress($assignments, $subjectId, $userId);
            
            // Step 5: Prepare current week info
            $currentWeekInfo = null;
            if ($currentWeekAssignment && $currentWeekAssignment->weeks) {
                $week = $currentWeekAssignment->weeks;
                $currentWeekInfo = [
                    'week_number' => $week->week_number,
                    'week_label' => "Week {$week->week_number}",
                    'start_date' => $week->start_date,
                    'end_date' => $week->end_date,
                    'start_end_date' => Carbon::parse($week->start_date)->format('d M Y') . 
                                       ' to ' . 
                                       Carbon::parse($week->end_date)->format('d M Y'),
                    'status' => 'current',
                    'assignment_id' => $currentWeekAssignment->id
                ];
            }
            
            \Log::info('=== FLOW: Progress Calculated (WeeklyPerformance) ===', [
                'subject_id' => $subjectId,
                'subject_name' => $subject->name,
                'current_week_info' => $currentWeekInfo,
                'progress_summary' => $progressData
            ]);

            $response = [
                'success' => true,
                'message' => 'Weekly performance data fetched successfully.',
                'data' => [
                    'subject_info' => [
                        'id' => $subject->id,
                        'name' => $subject->name
                    ],
                    'current_week_info' => $currentWeekInfo,
                    'progress_summary' => $progressData
                ]
            ];

            return response()->json($response, 200);

        } catch (\Exception $e) {
            return errorLog("Failed to fetch weekly performance data: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    /**
     * Calculate comprehensive subject progress
     */
    private function calculateSubjectProgress($assignments, $subjectId, $userId)
    {
        $totalTopics = 0;
        $completedTopics = 0;
        $totalSubtopics = 0;
        $completedSubtopics = 0;
        $totalTopicTests = 0;
        $completedTopicTests = 0;
        $totalSubtopicTests = 0;
        $completedSubtopicTests = 0;

        foreach ($assignments as $assignment) {
            $assignmentMSRIds = $assignment->manageStudentRecord->pluck('id');
            
            // Get topics from this assignment
            $topicMSRs = ManageStudentRecord::whereIn('parent_id', $assignmentMSRIds)
                ->where('model_type', 'App\\Models\\CourseTopic')
                ->with('modelable.subject')
                ->get();
                
            foreach ($topicMSRs as $topicMSR) {
                $topic = $topicMSR->modelable;
                
                // Manual check if modelable is null
                if (!$topic) {
                    $topic = \App\Models\CourseTopic::find($topicMSR->model_id);
                    if ($topic) {
                        $topic->load('subject');
                    }
                }
                
                // Filter by subject
                if ($topic && $topic->subject_id == $subjectId) {
                    $totalTopics++;
                    
                    // Check if topic is completed
                    $topicCompletion = ManageStudentRecord::where('model_type', 'App\\Models\\CourseTopic')
                        ->where('model_id', $topic->id)
                        ->where('buyer_id', $userId)
                        ->first();
                    
                    if ($topicCompletion && $topicCompletion->is_completed == 1) {
                        $completedTopics++;
                    }
                    
                    // Get topic tests
                    $topicTestMSRs = ManageStudentRecord::where('parent_id', $topicMSR->id)
                        ->where('model_type', 'App\\Models\\CourseTest')
                        ->get();
                    
                    foreach ($topicTestMSRs as $testMSR) {
                        $totalTopicTests++;
                        
                        // Check if test is completed (has answers)
                        $testAnswers = \App\Models\UserTestAnswer::where('user_id', $userId)
                            ->where('test_id', $testMSR->model_id)
                            ->exists();
                        
                        if ($testAnswers) {
                            $completedTopicTests++;
                        }
                    }
                    
                    // Get subtopics from this topic
                    $subtopicMSRs = ManageStudentRecord::where('parent_id', $topicMSR->id)
                        ->where('model_type', 'App\\Models\\CourseSubTopic')
                        ->with('modelable')
                        ->get();
                    
                    foreach ($subtopicMSRs as $subtopicMSR) {
                        $subtopic = $subtopicMSR->modelable;
                        
                        // Manual check if subtopic modelable is null
                        if (!$subtopic) {
                            $subtopic = \App\Models\CourseSubTopic::find($subtopicMSR->model_id);
                        }
                        
                        if ($subtopic) {
                            $totalSubtopics++;
                            
                            // Check if subtopic is completed
                            $subtopicCompletion = ManageStudentRecord::where('model_type', 'App\\Models\\CourseSubTopic')
                                ->where('model_id', $subtopic->id)
                                ->where('buyer_id', $userId)
                                ->first();
                            
                            if ($subtopicCompletion && $subtopicCompletion->is_completed == 1) {
                                $completedSubtopics++;
                            }
                            
                            // Get subtopic tests
                            $subtopicTestMSRs = ManageStudentRecord::where('parent_id', $subtopicMSR->id)
                                ->where('model_type', 'App\\Models\\CourseTest')
                                ->get();
                            
                            foreach ($subtopicTestMSRs as $testMSR) {
                                $totalSubtopicTests++;
                                
                                // Check if test is completed (has answers)
                                $testAnswers = \App\Models\UserTestAnswer::where('user_id', $userId)
                                    ->where('test_id', $testMSR->model_id)
                                    ->exists();
                                
                                if ($testAnswers) {
                                    $completedSubtopicTests++;
                                }
                            }
                        }
                    }
                }
            }
        }

        return [
            'total_topics' => $totalTopics,
            'completed_topics' => $completedTopics,
            'total_subtopics' => $totalSubtopics,
            'completed_subtopics' => $completedSubtopics,
            'total_topic_tests' => $totalTopicTests,
            'completed_topic_tests' => $completedTopicTests,
            'total_subtopic_tests' => $totalSubtopicTests,
            'completed_subtopic_tests' => $completedSubtopicTests,
            'topic_completion_percentage' => $totalTopics > 0 ? round(($completedTopics / $totalTopics) * 100, 2) : 0,
            'subtopic_completion_percentage' => $totalSubtopics > 0 ? round(($completedSubtopics / $totalSubtopics) * 100, 2) : 0,
            'topic_test_completion_percentage' => $totalTopicTests > 0 ? round(($completedTopicTests / $totalTopicTests) * 100, 2) : 0,
            'subtopic_test_completion_percentage' => $totalSubtopicTests > 0 ? round(($completedSubtopicTests / $totalSubtopicTests) * 100, 2) : 0,
            'overall_completion_percentage' => ($totalTopics + $totalSubtopics + $totalTopicTests + $totalSubtopicTests) > 0 ? 
                round((($completedTopics + $completedSubtopics + $completedTopicTests + $completedSubtopicTests) / 
                      ($totalTopics + $totalSubtopics + $totalTopicTests + $totalSubtopicTests)) * 100, 2) : 0
        ];
    }
}
