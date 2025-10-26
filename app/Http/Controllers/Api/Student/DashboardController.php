<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseAssignment;
use App\Models\CourseTest;
use App\Models\CourseTopic;
use App\Models\CourseSubTopic;
use App\Models\ManageStudentRecord;
use App\Models\UserTestAnswer;
use App\Http\Requests\SubjectDashboardRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Get comprehensive dashboard data for student
     */
    public function getDashboardData(Request $request)
    {
        try {
            $userId = auth()->id();
            $currentDate = Carbon::now();
            $currentDateString = $currentDate->format('Y-m-d');
            
            \Log::info('=== FLOW: Dashboard Data Request (Dashboard) ===', [
                'user_id' => $userId,
                'current_date' => $currentDateString,
                'timestamp' => $currentDate->toDateTimeString()
            ]);

            // Step 1: Get user's courses
            $courses = Course::whereHas(
                'manageStudentRecord',
                fn($q) => $q->where('buyer_id', $userId)
            )
            ->with(['manageStudentRecord:id,model_id,model_type'])
            ->get();

            if ($courses->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'No courses found for this user.',
                    'data' => $this->getEmptyDashboardData()
                ], 200);
            }

            $courseIds = $courses->flatMap(fn($course) => $course->manageStudentRecord->pluck('id'))->values();
            
            \Log::info('=== FLOW: Courses Found (Dashboard) ===', [
                'courses_count' => $courses->count(),
                'actual_course_ids' => $courses->pluck('id')->toArray(),
                'course_msr_ids' => $courseIds->toArray()
            ]);

            // Step 2: Get all assignments with weeks
            $assignments = CourseAssignment::with(['weeks', 'manageStudentRecord', 'acdemicCourses.courses'])
                ->whereHas('manageStudentRecord', fn($q) => $q->whereIn('parent_id', $courseIds))
                ->get();

            if ($assignments->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'No assignments found.',
                    'data' => $this->getEmptyDashboardData()
                ], 200);
            }

            \Log::info('=== FLOW: Assignments Found (Dashboard) ===', [
                'assignments_count' => $assignments->count(),
                'actual_assignment_ids' => $assignments->pluck('id')->toArray(),
                'assignments_details' => $assignments->map(fn($a) => [
                    'assignment_id' => $a->id,
                    'week_number' => $a->weeks ? $a->weeks->week_number : null,
                    'week_start_date' => $a->weeks ? $a->weeks->start_date : null,
                    'week_end_date' => $a->weeks ? $a->weeks->end_date : null,
                    'course_name' => $a->acdemicCourses && $a->acdemicCourses->courses ? $a->acdemicCourses->courses->name : 'Unknown'
                ])->toArray()
            ]);

            // Step 3: Categorize assignments by week status
            $pastAssignments = collect();
            $currentAssignments = collect();
            $futureAssignments = collect();

            foreach ($assignments as $assignment) {
                if (!$assignment->weeks) continue;

                $week = $assignment->weeks;
                $weekStartDate = Carbon::parse($week->start_date);
                $weekEndDate = Carbon::parse($week->end_date);

                if ($currentDate->between($weekStartDate, $weekEndDate)) {
                    $currentAssignments->push($assignment);
                } elseif ($currentDate->greaterThan($weekEndDate)) {
                    $pastAssignments->push($assignment);
                } else {
                    $futureAssignments->push($assignment);
                }
            }
            
            \Log::info('=== FLOW: Assignments Categorized (Dashboard) ===', [
                'past_assignments' => $pastAssignments->count(),
                'current_assignments' => $currentAssignments->count(),
                'future_assignments' => $futureAssignments->count(),
                'current_date' => $currentDateString
            ]);

            // Step 4: Get assignment IDs for different categories
            $pastAssignmentIds = $pastAssignments->flatMap(fn($assignment) => $assignment->manageStudentRecord->pluck('id'))->values();
            $currentAssignmentIds = $currentAssignments->flatMap(fn($assignment) => $assignment->manageStudentRecord->pluck('id'))->values();
            $futureAssignmentIds = $futureAssignments->flatMap(fn($assignment) => $assignment->manageStudentRecord->pluck('id'))->values();
            
            // Combine past + current for completed topics calculation
            $pastCurrentAssignmentIds = $pastAssignmentIds->merge($currentAssignmentIds);

            // Step 5: Get subject-wise breakdown
            $subjectWiseData = $this->getSubjectWiseBreakdown($assignments, $userId);

            // Step 6: Get current week info
            $currentWeekInfo = $this->getCurrentWeekInfo($currentAssignments);

            // Step 7: Calculate overall completion statistics
            $overallStats = $this->getOverallCompletionStats($subjectWiseData);
            
            \Log::info('=== FLOW: Dashboard Data Compiled (Dashboard) ===', [
                'subject_wise_data_count' => count($subjectWiseData),
                'current_week_info' => $currentWeekInfo,
                'overall_stats' => $overallStats,
                'summary' => [
                    'total_assignments' => $assignments->count(),
                    'past_weeks' => $pastAssignments->count(),
                    'current_weeks' => $currentAssignments->count(),
                    'future_weeks' => $futureAssignments->count()
                ]
            ]);

            $response = [
                'success' => true,
                'message' => 'Dashboard data fetched successfully.',
                'data' => [
                    'current_week_info' => $currentWeekInfo,
                    'subject_wise_breakdown' => $subjectWiseData,
                    'overall_completion' => $overallStats,
                    'summary' => [
                        'total_subjects' => count($subjectWiseData),
                        'total_assignments' => $assignments->count(),
                        'past_weeks' => $pastAssignments->count(),
                        'current_weeks' => $currentAssignments->count(),
                        'future_weeks' => $futureAssignments->count()
                    ]
                ]
            ];

            return response()->json($response, 200);

        } catch (\Exception $e) {
            return errorLog("Failed to fetch dashboard data: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    /**
     * Get subject-specific dashboard data
     */
    public function getSubjectDashboardData(SubjectDashboardRequest $request)
    {
        try {
            $userId = auth()->id();
            $subjectId = $request->subject_id;
            $currentDate = Carbon::now();
            $currentDateString = $currentDate->format('Y-m-d');
            
            \Log::info('=== FLOW: Subject Dashboard Request (Dashboard) ===', [
                'user_id' => $userId,
                'subject_id' => $subjectId,
                'current_date' => $currentDateString,
                'timestamp' => $currentDate->toDateTimeString()
            ]);

            // Step 1: Get user's courses for this specific subject
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
                    'data' => $this->getEmptySubjectDashboardData($subjectId)
                ], 200);
            }

            $courseIds = $courses->flatMap(fn($course) => $course->manageStudentRecord->pluck('id'))->values();
            
            \Log::info('=== FLOW: Subject Courses Found (Dashboard) ===', [
                'subject_id' => $subjectId,
                'courses_count' => $courses->count(),
                'actual_course_ids' => $courses->pluck('id')->toArray(),
                'course_msr_ids' => $courseIds->toArray()
            ]);

            // Step 2: Get assignments for this subject
            $assignments = CourseAssignment::with(['weeks', 'manageStudentRecord', 'acdemicCourses.courses'])
                ->whereHas('manageStudentRecord', fn($q) => $q->whereIn('parent_id', $courseIds))
                ->get();

            if ($assignments->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'No assignments found for this subject.',
                    'data' => $this->getEmptySubjectDashboardData($subjectId)
                ], 200);
            }

            // Step 3: Categorize assignments by week status
            $pastAssignments = collect();
            $currentAssignments = collect();
            $futureAssignments = collect();

            foreach ($assignments as $assignment) {
                if (!$assignment->weeks) continue;

                $week = $assignment->weeks;
                $weekStartDate = Carbon::parse($week->start_date);
                $weekEndDate = Carbon::parse($week->end_date);

                if ($currentDate->between($weekStartDate, $weekEndDate)) {
                    $currentAssignments->push($assignment);
                } elseif ($currentDate->greaterThan($weekEndDate)) {
                    $pastAssignments->push($assignment);
                } else {
                    $futureAssignments->push($assignment);
                }
            }

            // Step 4: Get subject-specific breakdown
            $subjectData = $this->getSpecificSubjectBreakdown($assignments, $userId, $subjectId);

            // Step 5: Get current week info
            $currentWeekInfo = $this->getCurrentWeekInfo($currentAssignments);

            \Log::info('=== FLOW: Subject Dashboard Compiled (Dashboard) ===', [
                'subject_id' => $subjectId,
                'subject_data' => $subjectData,
                'current_week_info' => $currentWeekInfo,
                'assignments_breakdown' => [
                    'past' => $pastAssignments->count(),
                    'current' => $currentAssignments->count(),
                    'future' => $futureAssignments->count()
                ]
            ]);

            $response = [
                'success' => true,
                'message' => 'Subject dashboard data fetched successfully.',
                'data' => [
                    'subject_info' => [
                        'subject_id' => $subjectId,
                        'subject_name' => $subjectData['subject_name'] ?? 'Unknown Subject'
                    ],
                    'current_week_info' => $currentWeekInfo,
                    'subject_breakdown' => $subjectData,
                    'summary' => [
                        'total_assignments' => $assignments->count(),
                        'past_weeks' => $pastAssignments->count(),
                        'current_weeks' => $currentAssignments->count(),
                        'future_weeks' => $futureAssignments->count()
                    ]
                ]
            ];

            return response()->json($response, 200);

        } catch (\Exception $e) {
            return errorLog("Failed to fetch subject dashboard data: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    /**
     * Get subject-wise breakdown with completion stats
     */
    private function getSubjectWiseBreakdown($assignments, $userId)
    {
        $subjectWiseData = [];
        
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
                    $topic = CourseTopic::find($topicMSR->model_id);
                    if ($topic) {
                        $topic->load('subject');
                    }
                }
                
                if ($topic && $topic->subject) {
                    $subjectId = $topic->subject->id;
                    $subjectName = $topic->subject->name;
                    
                    // Initialize subject data if not exists
                    if (!isset($subjectWiseData[$subjectId])) {
                        $subjectWiseData[$subjectId] = [
                            'subject_id' => $subjectId,
                            'subject_name' => $subjectName,
                            'total_topics' => 0,
                            'completed_topics' => 0,
                            'total_subtopics' => 0,
                            'completed_subtopics' => 0,
                            'total_topic_tests' => 0,
                            'completed_topic_tests' => 0,
                            'total_subtopic_tests' => 0,
                            'completed_subtopic_tests' => 0
                        ];
                    }
                    
                    // Count topic
                    $subjectWiseData[$subjectId]['total_topics']++;
                    
                    // Check if topic is completed
                    $topicCompleted = $topicMSR->is_completed == 1;
                    if ($topicCompleted) {
                        $subjectWiseData[$subjectId]['completed_topics']++;
                    }
                    
                    // Get topic tests
                    $topicTestMSRs = ManageStudentRecord::where('parent_id', $topicMSR->id)
                        ->where('model_type', 'App\\Models\\CourseTest')
                        ->get();
                    
                    $subjectWiseData[$subjectId]['total_topic_tests'] += $topicTestMSRs->count();
                    $subjectWiseData[$subjectId]['completed_topic_tests'] += $topicTestMSRs->where('is_completed', 1)->count();
                    
                    // Get subtopics from this topic
                    $subtopicMSRs = ManageStudentRecord::where('parent_id', $topicMSR->id)
                        ->where('model_type', 'App\\Models\\CourseSubTopic')
                        ->get();
                    
                    foreach ($subtopicMSRs as $subtopicMSR) {
                        // Count subtopic
                        $subjectWiseData[$subjectId]['total_subtopics']++;
                        
                        // Check if subtopic is completed
                        if ($subtopicMSR->is_completed == 1) {
                            $subjectWiseData[$subjectId]['completed_subtopics']++;
                        }
                        
                        // Get subtopic tests
                        $subtopicTestMSRs = ManageStudentRecord::where('parent_id', $subtopicMSR->id)
                            ->where('model_type', 'App\\Models\\CourseTest')
                            ->get();
                        
                        $subjectWiseData[$subjectId]['total_subtopic_tests'] += $subtopicTestMSRs->count();
                        $subjectWiseData[$subjectId]['completed_subtopic_tests'] += $subtopicTestMSRs->where('is_completed', 1)->count();
                    }
                }
            }
        }
        
        // Calculate completion percentages
        foreach ($subjectWiseData as &$subjectData) {
            $totalContent = $subjectData['total_topics'] + $subjectData['total_subtopics'];
            $completedContent = $subjectData['completed_topics'] + $subjectData['completed_subtopics'];
            $subjectData['content_completion_percentage'] = $totalContent > 0 ? round(($completedContent / $totalContent) * 100, 2) : 0;
            
            $totalTests = $subjectData['total_topic_tests'] + $subjectData['total_subtopic_tests'];
            $completedTests = $subjectData['completed_topic_tests'] + $subjectData['completed_subtopic_tests'];
            $subjectData['test_completion_percentage'] = $totalTests > 0 ? round(($completedTests / $totalTests) * 100, 2) : 0;
        }
        
        return array_values($subjectWiseData);
    }

    /**
     * Get current week information
     */
    private function getCurrentWeekInfo($currentAssignments)
    {
        if ($currentAssignments->isEmpty()) {
            return [
                'week_number' => null,
                'week_range' => null,
                'start_date' => null,
                'end_date' => null,
                'status' => 'no_current_week',
                'days_remaining' => 0,
                'time_remaining' => '00:00:00',
                'time_format' => 'time'
            ];
        }

        $currentAssignment = $currentAssignments->first();
        $week = $currentAssignment->weeks;
        
        if (!$week) {
            return [
                'week_number' => null,
                'week_range' => null,
                'start_date' => null,
                'end_date' => null,
                'status' => 'no_week_data',
                'days_remaining' => 0,
                'time_remaining' => '00:00:00',
                'time_format' => 'time'
            ];
        }

        $startDate = Carbon::parse($week->start_date);
        $endDate = Carbon::parse($week->end_date);
        $currentDate = Carbon::now();
        
        // Calculate time remaining
        $timeRemaining = $this->calculateTimeRemaining($currentDate, $endDate);

        return [
            'week_number' => $week->week_number,
            'week_range' => $startDate->format('d M Y') . ' to ' . $endDate->format('d M Y'),
            'start_date' => $week->start_date,
            'end_date' => $week->end_date,
            'status' => 'current',
            'days_remaining' => $timeRemaining['days_remaining'],
            'time_remaining' => $timeRemaining['time_remaining'],
            'time_format' => $timeRemaining['format'], // 'days' or 'time'
            'course_name' => $currentAssignment->acdemicCourses && $currentAssignment->acdemicCourses->courses 
                ? $currentAssignment->acdemicCourses->courses->name 
                : 'Unknown Course'
        ];
    }

    /**
     * Get overall completion statistics
     */
    private function getOverallCompletionStats($subjectWiseData)
    {
        if (empty($subjectWiseData)) {
            return [
                'total_subjects' => 0,
                'total_topics' => 0,
                'completed_topics' => 0,
                'total_subtopics' => 0,
                'completed_subtopics' => 0,
                'total_tests' => 0,
                'completed_tests' => 0,
                'overall_content_percentage' => 0,
                'overall_test_percentage' => 0
            ];
        }

        $totalTopics = array_sum(array_column($subjectWiseData, 'total_topics'));
        $completedTopics = array_sum(array_column($subjectWiseData, 'completed_topics'));
        $totalSubtopics = array_sum(array_column($subjectWiseData, 'total_subtopics'));
        $completedSubtopics = array_sum(array_column($subjectWiseData, 'completed_subtopics'));
        $totalTopicTests = array_sum(array_column($subjectWiseData, 'total_topic_tests'));
        $completedTopicTests = array_sum(array_column($subjectWiseData, 'completed_topic_tests'));
        $totalSubtopicTests = array_sum(array_column($subjectWiseData, 'total_subtopic_tests'));
        $completedSubtopicTests = array_sum(array_column($subjectWiseData, 'completed_subtopic_tests'));

        $totalContent = $totalTopics + $totalSubtopics;
        $completedContent = $completedTopics + $completedSubtopics;
        $totalTests = $totalTopicTests + $totalSubtopicTests;
        $completedTests = $completedTopicTests + $completedSubtopicTests;

        return [
            'total_subjects' => count($subjectWiseData),
            'total_topics' => $totalTopics,
            'completed_topics' => $completedTopics,
            'total_subtopics' => $totalSubtopics,
            'completed_subtopics' => $completedSubtopics,
            'total_tests' => $totalTests,
            'completed_tests' => $completedTests,
            'overall_content_percentage' => $totalContent > 0 ? round(($completedContent / $totalContent) * 100, 2) : 0,
            'overall_test_percentage' => $totalTests > 0 ? round(($completedTests / $totalTests) * 100, 2) : 0
        ];
    }

    /**
     * Get weekly progress data for chart
     */
    private function getWeeklyProgressData($assignments, $userId, $currentDate)
    {
        $weeklyProgress = [];

        foreach ($assignments as $assignment) {
            if (!$assignment->weeks) continue;

            $week = $assignment->weeks;
            $weekStartDate = Carbon::parse($week->start_date);
            $weekEndDate = Carbon::parse($week->end_date);

            // Skip future weeks that haven't started
            if ($weekStartDate->greaterThan($currentDate)) {
                continue;
            }

            $assignmentMSRIds = $assignment->manageStudentRecord->pluck('id');

            // Get week tests
            $weekTests = CourseTest::whereHas('manageStudentRecord', fn($q) => $q->whereIn('parent_id', $assignmentMSRIds))
                ->get();

            if ($weekTests->isEmpty()) {
                continue;
            }

            $testIds = $weekTests->pluck('id');

            // Get test results for this week
            $testAnswers = UserTestAnswer::where('user_id', $userId)
                ->whereIn('test_id', $testIds)
                ->get();

            // Calculate week statistics
            $completedTestIds = $testAnswers->pluck('test_id')->unique();
            $testsCompleted = $completedTestIds->count();
            $totalTests = $weekTests->count();

            // Calculate average percentage for completed tests
            $testResults = $testAnswers->groupBy('test_id')->map(function($answers) {
                $correctAnswers = $answers->where('is_correct', true)->count();
                $totalQuestions = $answers->count();
                return $totalQuestions > 0 ? round(($correctAnswers / $totalQuestions) * 100, 2) : 0;
            });

            $averagePercentage = $testResults->count() > 0 ? round($testResults->avg(), 2) : 0;
            $completionPercentage = $totalTests > 0 ? round(($testsCompleted / $totalTests) * 100, 2) : 0;

            $weeklyProgress[] = [
                'week_number' => $week->week_number,
                'week_label' => "Week " . $week->week_number,
                'start_date' => $week->start_date,
                'end_date' => $week->end_date,
                'status' => $this->getWeekStatus($week->start_date, $week->end_date),
                'tests_completed' => $testsCompleted,
                'total_tests' => $totalTests,
                'completion_percentage' => $completionPercentage,
                'average_score_percentage' => $averagePercentage
            ];
        }

        // Sort by week number
        usort($weeklyProgress, function($a, $b) {
            return $a['week_number'] <=> $b['week_number'];
        });

        return $weeklyProgress;
    }

    /**
     * Get week status based on dates
     */
    private function getWeekStatus($startDate, $endDate)
    {
        $now = Carbon::now();
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        if ($now->lt($start)) {
            return 'upcoming';
        } elseif ($now->between($start, $end)) {
            return 'current';
        } else {
            return 'completed';
        }
    }

    /**
     * Get empty dashboard data structure
     */
    private function getEmptyDashboardData()
    {
        return [
            'topic_completion' => [
                'completed_topics' => 0,
                'remaining_topics' => 0,
                'total_topics' => 0,
                'completion_percentage' => 0
            ],
            'upcoming_tests' => [
                'count' => 0,
                'description' => 'No upcoming tests found'
            ],
            'current_week_progress' => [
                'week_number' => null,
                'progress_percentage' => 0,
                'tests_completed' => 0,
                'total_tests' => 0,
                'average_score' => 0,
                'status' => 'no_data'
            ],
            'weekly_progress' => [],
            'summary' => [
                'total_weeks' => 0,
                'past_weeks' => 0,
                'current_weeks' => 0,
                'future_weeks' => 0
            ]
        ];
    }

    /**
     * Get empty subject dashboard data structure
     */
    private function getEmptySubjectDashboardData($subjectId)
    {
        return [
            'subject_info' => [
                'subject_id' => $subjectId,
                'subject_name' => 'Unknown Subject'
            ],
            'current_week_info' => [
                'week_number' => null,
                'week_range' => null,
                'start_date' => null,
                'end_date' => null,
                'status' => 'no_current_week',
                'days_remaining' => 0
            ],
            'subject_breakdown' => [
                'subject_id' => $subjectId,
                'subject_name' => 'Unknown Subject',
                'total_topics' => 0,
                'completed_topics' => 0,
                'total_subtopics' => 0,
                'completed_subtopics' => 0,
                'total_topic_tests' => 0,
                'completed_topic_tests' => 0,
                'total_subtopic_tests' => 0,
                'completed_subtopic_tests' => 0,
                'content_completion_percentage' => 0,
                'test_completion_percentage' => 0
            ],
            'summary' => [
                'total_assignments' => 0,
                'past_weeks' => 0,
                'current_weeks' => 0,
                'future_weeks' => 0
            ]
        ];
    }

    /**
     * Get specific subject breakdown with completion stats
     */
    private function getSpecificSubjectBreakdown($assignments, $userId, $subjectId)
    {
        $subjectData = [
            'subject_id' => $subjectId,
            'subject_name' => 'Unknown Subject',
            'total_topics' => 0,
            'completed_topics' => 0,
            'total_subtopics' => 0,
            'completed_subtopics' => 0,
            'total_topic_tests' => 0,
            'completed_topic_tests' => 0,
            'total_subtopic_tests' => 0,
            'completed_subtopic_tests' => 0
        ];
        
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
                    $topic = CourseTopic::find($topicMSR->model_id);
                    if ($topic) {
                        $topic->load('subject');
                    }
                }
                
                // Filter by specific subject
                if ($topic && $topic->subject && $topic->subject->id == $subjectId) {
                    $subjectData['subject_name'] = $topic->subject->name;
                    
                    // Count topic
                    $subjectData['total_topics']++;
                    
                    // Check if topic is completed
                    if ($topicMSR->is_completed == 1) {
                        $subjectData['completed_topics']++;
                    }
                    
                    // Get topic tests
                    $topicTestMSRs = ManageStudentRecord::where('parent_id', $topicMSR->id)
                        ->where('model_type', 'App\\Models\\CourseTest')
                        ->get();
                    
                    $subjectData['total_topic_tests'] += $topicTestMSRs->count();
                    $subjectData['completed_topic_tests'] += $topicTestMSRs->where('is_completed', 1)->count();
                    
                    // Get subtopics from this topic
                    $subtopicMSRs = ManageStudentRecord::where('parent_id', $topicMSR->id)
                        ->where('model_type', 'App\\Models\\CourseSubTopic')
                        ->get();
                    
                    foreach ($subtopicMSRs as $subtopicMSR) {
                        // Count subtopic
                        $subjectData['total_subtopics']++;
                        
                        // Check if subtopic is completed
                        if ($subtopicMSR->is_completed == 1) {
                            $subjectData['completed_subtopics']++;
                        }
                        
                        // Get subtopic tests
                        $subtopicTestMSRs = ManageStudentRecord::where('parent_id', $subtopicMSR->id)
                            ->where('model_type', 'App\\Models\\CourseTest')
                            ->get();
                        
                        $subjectData['total_subtopic_tests'] += $subtopicTestMSRs->count();
                        $subjectData['completed_subtopic_tests'] += $subtopicTestMSRs->where('is_completed', 1)->count();
                    }
                }
            }
        }
        
        // Calculate completion percentages
        $totalContent = $subjectData['total_topics'] + $subjectData['total_subtopics'];
        $completedContent = $subjectData['completed_topics'] + $subjectData['completed_subtopics'];
        $subjectData['content_completion_percentage'] = $totalContent > 0 ? round(($completedContent / $totalContent) * 100, 2) : 0;
        
        $totalTests = $subjectData['total_topic_tests'] + $subjectData['total_subtopic_tests'];
        $completedTests = $subjectData['completed_topic_tests'] + $subjectData['completed_subtopic_tests'];
        $subjectData['test_completion_percentage'] = $totalTests > 0 ? round(($completedTests / $totalTests) * 100, 2) : 0;
        
        return $subjectData;
    }

    /**
     * Calculate time remaining with smart formatting
     */
    private function calculateTimeRemaining($currentDate, $endDate)
    {
        // If end date has passed, return 0
        if ($currentDate->greaterThan($endDate)) {
            return [
                'days_remaining' => 0,
                'time_remaining' => '00:00:00',
                'format' => 'time'
            ];
        }

        // Calculate total difference
        $totalDays = $currentDate->diffInDays($endDate, false);
        $totalHours = $currentDate->diffInHours($endDate, false);
        $totalMinutes = $currentDate->diffInMinutes($endDate, false);
        $totalSeconds = $currentDate->diffInSeconds($endDate, false);

        // If more than 2 days remaining, show in days
        if ($totalDays > 2) {
            return [
                'days_remaining' => $totalDays,
                'time_remaining' => $totalDays . ' days',
                'format' => 'days'
            ];
        }

        // If 2 days or less, show in hh:mm:ss format
        $hours = floor($totalSeconds / 3600);
        $minutes = floor(($totalSeconds % 3600) / 60);
        $seconds = $totalSeconds % 60;

        $timeString = sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);

        return [
            'days_remaining' => $totalDays,
            'time_remaining' => $timeString,
            'format' => 'time'
        ];
    }
}
