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
     * Get weekly performance graph data for a specific subject
     */
    public function getWeeklyPerformance(WeeklyPerformanceRequest $request)
    {
        try {
            $userId = auth()->id();
            $subjectId = $request->subject_id;
            
            \Log::info('=== FLOW: Weekly Performance Request (WeeklyPerformance) ===', [
                'user_id' => $userId,
                'subject_id' => $subjectId,
                'timestamp' => Carbon::now()->toDateTimeString()
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
                        'weekly_performance' => [],
                        'summary' => [
                            'total_weeks' => 0,
                            'average_score' => 0,
                            'highest_score' => 0,
                            'lowest_score' => 0,
                            'total_tests' => 0
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

            // Step 2: Get assignments with past weeks + current week only
            $currentDate = Carbon::now()->format('Y-m-d');
            $assignments = CourseAssignment::with(['weeks', 'manageStudentRecord', 'acdemicCourses.courses'])
                ->whereHas('manageStudentRecord', fn($q) => $q->whereIn('parent_id', $courseIds))
                ->whereHas('weeks', function($q) use ($currentDate) {
                    $q->where('start_date', '<=', $currentDate);
                })
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
                        'weekly_performance' => [],
                        'summary' => [
                            'total_weeks' => 0,
                            'average_score' => 0,
                            'highest_score' => 0,
                            'lowest_score' => 0,
                            'total_tests' => 0
                        ]
                    ]
                ], 200);
            }

            \Log::info('=== FLOW: Assignments Found (WeeklyPerformance) ===', [
                'assignments_count' => $assignments->count(),
                'actual_assignment_ids' => $assignments->pluck('id')->toArray(),
                'assignments_details' => $assignments->map(fn($a) => [
                    'assignment_id' => $a->id,
                    'week_number' => $a->weeks ? $a->weeks->week_number : null,
                    'week_start_date' => $a->weeks ? $a->weeks->start_date : null,
                    'week_end_date' => $a->weeks ? $a->weeks->end_date : null,
                    'course_name' => $a->acdemicCourses && $a->acdemicCourses->courses ? $a->acdemicCourses->courses->name : 'Unknown'
                ])->toArray(),
                'current_date' => $currentDate
            ]);

            // Step 3: Get assignment IDs
            $assignmentIds = $assignments->flatMap(fn($assignment) => $assignment->manageStudentRecord->pluck('id'))->values();

            // Step 4: Get tests for this subject using MSR approach
            $allTestsData = [];
            
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
                        // Get topic tests
                        $topicTests = CourseTest::whereHas('manageStudentRecord', fn($q) => $q->where('parent_id', $topicMSR->id))
                            ->whereNull('course_sub_topic_id')
                            ->get();
                        
                        foreach ($topicTests as $test) {
                            $allTestsData[] = $test;
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
                        
                        // Filter by subject (subtopics inherit subject from topic)
                        if ($subtopic && $topic && $topic->subject_id == $subjectId) {
                            // Get subtopic tests
                            $subtopicTests = CourseTest::whereHas('manageStudentRecord', fn($q) => $q->where('parent_id', $subtopicMSR->id))
                                ->whereNotNull('course_sub_topic_id')
                                ->get();
                            
                            foreach ($subtopicTests as $test) {
                                $allTestsData[] = $test;
                            }
                        }
                    }
                }
            }
            
            $tests = collect($allTestsData)->unique('id');

            if ($tests->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'No tests found for this subject.',
                    'data' => [
                        'subject_info' => [
                            'id' => $subject->id,
                            'name' => $subject->name
                        ],
                        'weekly_performance' => [],
                        'summary' => [
                            'total_weeks' => 0,
                            'average_score' => 0,
                            'highest_score' => 0,
                            'lowest_score' => 0,
                            'total_tests' => 0
                        ]
                    ]
                ], 200);
            }

            $testIds = $tests->pluck('id');
            
            \Log::info('=== FLOW: Tests Found (WeeklyPerformance) ===', [
                'tests_count' => $tests->count(),
                'test_ids' => $testIds->toArray(),
                'subject_id' => $subjectId,
                'subject_name' => $subject->name,
                'tests_details' => $tests->map(fn($t) => [
                    'test_id' => $t->id,
                    'test_name' => $t->name,
                    'topic_name' => $t->courseTopic ? $t->courseTopic->name : null,
                    'subtopic_name' => $t->courseSubTopic ? $t->courseSubTopic->name : null
                ])->toArray()
            ]);

            // Step 5: Get test results
            $testAnswers = UserTestAnswer::where('user_id', $userId)
                ->whereIn('test_id', $testIds)
                ->with(['test'])
                ->get();

            // Calculate test results by test_id and attempt_number
            $testResults = $testAnswers->groupBy(['test_id', 'attempt_number'])->map(function($attempts, $testId) {
                return $attempts->map(function($answers, $attemptNumber) use ($testId) {
                    $test = $answers->first()->test;
                    $correctAnswers = $answers->where('is_correct', true)->count();
                    $totalQuestions = $answers->count();
                    $totalMarks = $test->total_marks ?? $totalQuestions;
                    $score = $totalMarks > 0 ? round(($correctAnswers / $totalQuestions) * $totalMarks, 2) : 0;
                    $percentage = $totalQuestions > 0 ? round(($correctAnswers / $totalQuestions) * 100, 2) : 0;
                    
                    return (object)[
                        'test_id' => $testId,
                        'test' => $test,
                        'score' => $score,
                        'percentage' => $percentage,
                        'total_questions' => $totalQuestions,
                        'correct_answers' => $correctAnswers,
                        'attempt_number' => $attemptNumber,
                        'completed_at' => $answers->max('created_at')
                    ];
                });
            })->flatten();

            // Get best attempts only
            $bestResults = $testResults->groupBy('test_id')->map(function($attempts) {
                return $attempts->sortByDesc('score')->first();
            })->values();
            
            \Log::info('=== FLOW: Test Results Calculated (WeeklyPerformance) ===', [
                'test_answers_count' => $testAnswers->count(),
                'unique_tests_attempted' => $testAnswers->pluck('test_id')->unique()->count(),
                'total_attempts' => $testResults->count(),
                'best_results_count' => $bestResults->count(),
                'sample_result' => $bestResults->count() > 0 ? [
                    'test_id' => $bestResults->first()->test_id,
                    'score' => $bestResults->first()->score,
                    'percentage' => $bestResults->first()->percentage,
                    'attempt_number' => $bestResults->first()->attempt_number
                ] : null
            ]);

            // Step 6: Group results by weeks
            $weeklyPerformance = [];
            $currentDate = Carbon::now();

            foreach ($assignments as $assignment) {
                if (!$assignment->weeks) continue;

                $week = $assignment->weeks;
                $weekStartDate = Carbon::parse($week->start_date);
                
                // Skip future weeks
                if ($weekStartDate->greaterThan($currentDate)) {
                    continue;
                }

                // Get assignment's manage student record IDs
                $assignmentMSRIds = $assignment->manageStudentRecord->pluck('id');

                // Find tests belonging to this assignment
                $assignmentTests = collect();
                
                // Get topic MSRs for this assignment
                $assignmentTopicMSRs = ManageStudentRecord::whereIn('parent_id', $assignmentMSRIds)
                    ->where('model_type', 'App\\Models\\CourseTopic')
                    ->get();
                
                foreach ($assignmentTopicMSRs as $topicMSR) {
                    // Get topic tests
                    $topicTests = $tests->filter(function($test) use ($topicMSR) {
                        return $test->manageStudentRecord->where('parent_id', $topicMSR->id)->isNotEmpty();
                    });
                    $assignmentTests = $assignmentTests->merge($topicTests);
                    
                    // Get subtopic MSRs for this topic
                    $subtopicMSRs = ManageStudentRecord::where('parent_id', $topicMSR->id)
                        ->where('model_type', 'App\\Models\\CourseSubTopic')
                        ->get();
                    
                    foreach ($subtopicMSRs as $subtopicMSR) {
                        $subtopicTests = $tests->filter(function($test) use ($subtopicMSR) {
                            return $test->manageStudentRecord->where('parent_id', $subtopicMSR->id)->isNotEmpty();
                        });
                        $assignmentTests = $assignmentTests->merge($subtopicTests);
                    }
                }
                
                $assignmentTests = $assignmentTests->unique('id');

                $assignmentTestIds = $assignmentTests->pluck('id');

                // Filter results for this assignment
                $weekResults = $bestResults->filter(function($result) use ($assignmentTestIds) {
                    return $assignmentTestIds->contains($result->test_id);
                });

                // Calculate week statistics
                $weekStats = $this->calculateWeekStats($weekResults);

                $weeklyPerformance[] = [
                    'week_number' => $week->week_number,
                    'week_label' => "Week " . $week->week_number,
                    'start_date' => $week->start_date,
                    'end_date' => $week->end_date,
                    'status' => $this->getWeekStatus($week->start_date, $week->end_date),
                    'tests_count' => $weekResults->count(),
                    'average_score' => $weekStats['average_score'],
                    'average_percentage' => $weekStats['average_percentage'],
                    'total_score' => $weekStats['total_score'],
                    'max_possible_score' => $weekStats['max_possible_score'],
                    'highest_score' => $weekStats['highest_score'],
                    'lowest_score' => $weekStats['lowest_score'],
                    'performance_trend' => $weekStats['performance_trend']
                ];
            }

            // Sort by week number
            usort($weeklyPerformance, function($a, $b) {
                return $a['week_number'] <=> $b['week_number'];
            });

            // Step 7: Calculate overall summary
            $allScores = collect($weeklyPerformance)->pluck('average_score')->filter();
            $totalTests = collect($weeklyPerformance)->sum('tests_count');

            $summary = [
                'total_weeks' => count($weeklyPerformance),
                'average_score' => $allScores->count() > 0 ? round($allScores->avg(), 2) : 0,
                'highest_score' => $allScores->count() > 0 ? $allScores->max() : 0,
                'lowest_score' => $allScores->count() > 0 ? $allScores->min() : 0,
                'total_tests' => $totalTests,
                'performance_trend' => $this->calculateOverallTrend($weeklyPerformance)
            ];
            
            \Log::info('=== FLOW: Weekly Performance Compiled (WeeklyPerformance) ===', [
                'weekly_performance_count' => count($weeklyPerformance),
                'summary' => $summary,
                'subject_info' => [
                    'id' => $subject->id,
                    'name' => $subject->name
                ],
                'sample_week' => count($weeklyPerformance) > 0 ? [
                    'week_number' => $weeklyPerformance[0]['week_number'],
                    'tests_count' => $weeklyPerformance[0]['tests_count'],
                    'average_score' => $weeklyPerformance[0]['average_score'],
                    'performance_trend' => $weeklyPerformance[0]['performance_trend']
                ] : null
            ]);

            $response = [
                'success' => true,
                'message' => 'Weekly performance data fetched successfully.',
                'data' => [
                    'subject_info' => [
                        'id' => $subject->id,
                        'name' => $subject->name
                    ],
                    'weekly_performance' => $weeklyPerformance,
                    'summary' => $summary
                ]
            ];

            return response()->json($response, 200);

        } catch (\Exception $e) {
            return errorLog("Failed to fetch weekly performance data: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    /**
     * Calculate week statistics
     */
    private function calculateWeekStats($weekResults)
    {
        if ($weekResults->isEmpty()) {
            return [
                'average_score' => 0,
                'average_percentage' => 0,
                'total_score' => 0,
                'max_possible_score' => 0,
                'highest_score' => 0,
                'lowest_score' => 0,
                'performance_trend' => 'no_data'
            ];
        }

        $scores = $weekResults->pluck('score');
        $percentages = $weekResults->pluck('percentage');
        $totalScore = $scores->sum();
        $maxPossibleScore = $weekResults->sum(function($result) {
            return $result->test->total_marks ?? $result->total_questions;
        });

        return [
            'average_score' => round($scores->avg(), 2),
            'average_percentage' => round($percentages->avg(), 2),
            'total_score' => $totalScore,
            'max_possible_score' => $maxPossibleScore,
            'highest_score' => $scores->max(),
            'lowest_score' => $scores->min(),
            'performance_trend' => $this->getPerformanceTrend($percentages->avg())
        ];
    }

    /**
     * Get performance trend based on average percentage
     */
    private function getPerformanceTrend($averagePercentage)
    {
        if ($averagePercentage >= 80) return 'excellent';
        if ($averagePercentage >= 60) return 'good';
        if ($averagePercentage >= 40) return 'average';
        return 'needs_improvement';
    }

    /**
     * Calculate overall performance trend
     */
    private function calculateOverallTrend($weeklyPerformance)
    {
        if (count($weeklyPerformance) < 2) {
            return 'insufficient_data';
        }

        $scores = array_column($weeklyPerformance, 'average_score');
        $scores = array_filter($scores); // Remove zeros
        
        if (count($scores) < 2) {
            return 'insufficient_data';
        }

        $firstHalf = array_slice($scores, 0, ceil(count($scores) / 2));
        $secondHalf = array_slice($scores, floor(count($scores) / 2));
        
        $firstAvg = array_sum($firstHalf) / count($firstHalf);
        $secondAvg = array_sum($secondHalf) / count($secondHalf);
        
        if ($secondAvg > $firstAvg + 5) return 'improving';
        if ($secondAvg < $firstAvg - 5) return 'declining';
        return 'stable';
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
}
