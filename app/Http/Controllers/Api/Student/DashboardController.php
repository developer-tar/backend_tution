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

            // Step 5: Calculate topic completion statistics
            $topicStats = $this->getTopicCompletionStats($pastCurrentAssignmentIds, $userId);

            // Step 6: Calculate upcoming tests (current + future weeks)
            $upcomingCurrentAssignmentIds = $currentAssignmentIds->merge($futureAssignmentIds);
            $upcomingTestsCount = $this->getUpcomingTestsCount($upcomingCurrentAssignmentIds);

            // Step 7: Calculate current week progress
            $currentWeekProgress = $this->getCurrentWeekProgress($currentAssignmentIds, $userId);

            // Step 8: Get weekly progress data
            $weeklyProgress = $this->getWeeklyProgressData($assignments, $userId, $currentDate);
            
            \Log::info('=== FLOW: Dashboard Data Compiled (Dashboard) ===', [
                'topic_completion' => $topicStats,
                'upcoming_tests_count' => $upcomingTestsCount,
                'current_week_progress' => $currentWeekProgress,
                'weekly_progress_count' => count($weeklyProgress),
                'summary' => [
                    'total_weeks' => count($weeklyProgress),
                    'past_weeks' => $pastAssignments->count(),
                    'current_weeks' => $currentAssignments->count(),
                    'future_weeks' => $futureAssignments->count()
                ]
            ]);

            $response = [
                'success' => true,
                'message' => 'Dashboard data fetched successfully.',
                'data' => [
                    'topic_completion' => [
                        'completed_topics' => $topicStats['completed'],
                        'remaining_topics' => $topicStats['remaining'],
                        'total_topics' => $topicStats['total'],
                        'completion_percentage' => $topicStats['completion_percentage']
                    ],
                    'upcoming_tests' => [
                        'count' => $upcomingTestsCount,
                        'description' => 'Tests in current and future weeks'
                    ],
                    'current_week_progress' => $currentWeekProgress,
                    'weekly_progress' => $weeklyProgress,
                    'summary' => [
                        'total_weeks' => count($weeklyProgress),
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
     * Get topic completion statistics
     */
    private function getTopicCompletionStats($assignmentIds, $userId)
    {
        if ($assignmentIds->isEmpty()) {
            return [
                'completed' => 0,
                'remaining' => 0,
                'total' => 0,
                'completion_percentage' => 0
            ];
        }

        // Get topic IDs from assignments
        $topicIds = ManageStudentRecord::whereIn('parent_id', $assignmentIds)->pluck('id');

        // Get all topics
        $allTopics = CourseTopic::whereHas('manageStudentRecord', fn($q) => $q->whereIn('parent_id', $topicIds))
            ->with(['manageStudentRecord' => fn($q) => $q->where('buyer_id', $userId)])
            ->get();

        // Get all subtopics
        $allSubTopics = CourseSubTopic::whereHas('manageStudentRecord', fn($q) => $q->whereIn('parent_id', $topicIds))
            ->with(['manageStudentRecord' => fn($q) => $q->where('buyer_id', $userId)])
            ->get();

        // Count completed topics
        $completedTopics = $allTopics->filter(function($topic) {
            $record = $topic->manageStudentRecord->first();
            return $record && $record->is_completed == config('constants.completed.YES');
        })->count();

        // Count completed subtopics
        $completedSubTopics = $allSubTopics->filter(function($subtopic) {
            $record = $subtopic->manageStudentRecord->first();
            return $record && $record->is_completed == config('constants.completed.YES');
        })->count();

        $totalCompleted = $completedTopics + $completedSubTopics;
        $totalContent = $allTopics->count() + $allSubTopics->count();
        $remaining = $totalContent - $totalCompleted;
        $completionPercentage = $totalContent > 0 ? round(($totalCompleted / $totalContent) * 100, 2) : 0;

        return [
            'completed' => $totalCompleted,
            'remaining' => $remaining,
            'total' => $totalContent,
            'completion_percentage' => $completionPercentage
        ];
    }

    /**
     * Get upcoming tests count
     */
    private function getUpcomingTestsCount($assignmentIds)
    {
        if ($assignmentIds->isEmpty()) {
            return 0;
        }

        return CourseTest::whereHas('manageStudentRecord', fn($q) => $q->whereIn('parent_id', $assignmentIds))
            ->count();
    }

    /**
     * Get current week progress based on test scores
     */
    private function getCurrentWeekProgress($currentAssignmentIds, $userId)
    {
        if ($currentAssignmentIds->isEmpty()) {
            return [
                'week_number' => null,
                'progress_percentage' => 0,
                'tests_completed' => 0,
                'total_tests' => 0,
                'average_score' => 0,
                'status' => 'no_current_week'
            ];
        }

        // Get current week tests
        $currentWeekTests = CourseTest::whereHas('manageStudentRecord', fn($q) => $q->whereIn('parent_id', $currentAssignmentIds))
            ->get();

        if ($currentWeekTests->isEmpty()) {
            return [
                'week_number' => null,
                'progress_percentage' => 0,
                'tests_completed' => 0,
                'total_tests' => 0,
                'average_score' => 0,
                'status' => 'no_tests_in_current_week'
            ];
        }

        $testIds = $currentWeekTests->pluck('id');

        // Get test results for current week
        $testAnswers = UserTestAnswer::where('user_id', $userId)
            ->whereIn('test_id', $testIds)
            ->with(['test'])
            ->get();

        // Calculate completed tests and scores
        $completedTestIds = $testAnswers->pluck('test_id')->unique();
        $testsCompleted = $completedTestIds->count();
        $totalTests = $currentWeekTests->count();

        // Calculate average score for completed tests
        $testResults = $testAnswers->groupBy('test_id')->map(function($answers, $testId) {
            $test = $answers->first()->test;
            $correctAnswers = $answers->where('is_correct', true)->count();
            $totalQuestions = $answers->count();
            $percentage = $totalQuestions > 0 ? round(($correctAnswers / $totalQuestions) * 100, 2) : 0;
            
            return $percentage;
        });

        $averageScore = $testResults->count() > 0 ? round($testResults->avg(), 2) : 0;
        $progressPercentage = $totalTests > 0 ? round(($testsCompleted / $totalTests) * 100, 2) : 0;

        return [
            'week_number' => 'Current Week',
            'progress_percentage' => $progressPercentage,
            'tests_completed' => $testsCompleted,
            'total_tests' => $totalTests,
            'average_score' => $averageScore,
            'status' => $testsCompleted == $totalTests ? 'completed' : 'in_progress'
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
}
