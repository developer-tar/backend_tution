<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\TestResultRequest;
use App\Models\Course;
use App\Models\CourseAssignment;
use App\Models\CourseTest;
use App\Models\UserTestAnswer;
use App\Models\ManageStudentRecord;
use App\Models\CourseTopic;
use App\Models\CourseSubTopic;
use App\Models\Week;
use Carbon\Carbon;

class TestResultController extends Controller
{
    /**
     * Get all test results by weeks with optional filtering
     */
    public function getTestResultsByWeeks(TestResultRequest $request)
    {
        try {
            $userId = auth()->id();
            
            // Get validated data from FormRequest
            $courseId = $request->course_id;
            $subjectId = $request->subject_id;
            $topicId = $request->topic_id;
            $subtopicId = $request->subtopic_id;
            $attempt = $request->attempt ?? 'all';
            
            \Log::info('=== FLOW: Attempt Filter (TestResults) ===', [
                'attempt_param' => $attempt,
                'filter_logic' => match($attempt) {
                    'first_attempt' => 'Only first attempt of each test',
                    'last_attempt' => 'Only last attempt of each test',
                    'all' => 'All attempts of each test',
                    default => 'All attempts (default)'
                }
            ]);

            // Step 1: Get user's courses with optional filtering
            $coursesQuery = Course::whereHas(
                'manageStudentRecord',
                fn($q) => $q->where('buyer_id', $userId)
            );

            // Apply course filter
            if ($courseId) {
                $coursesQuery->where('id', $courseId);
            }

            // Apply subject filter
            if ($subjectId) {
                $coursesQuery->whereHas('subjects', fn($q) => $q->where('subjects.id', $subjectId));
            }

            $courses = $coursesQuery->with(['manageStudentRecord:id,model_id,model_type'])->get();
            
            if ($courses->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'No courses found for the given criteria.',
                    'data' => []
                ], 200);
            }

            $courseIds = $courses->flatMap(fn($course) => $course->manageStudentRecord->pluck('id'))->values();

            \Log::info('=== FLOW: Courses Found ===', [
                'user_id' => $userId,
                'courses_count' => $courses->count(),
                'actual_course_ids' => $courses->pluck('id')->toArray(),
                'course_msr_ids' => $courseIds->toArray(),
                'courses_details' => $courses->map(fn($c) => [
                    'course_id' => $c->id,
                    'course_name' => $c->name,
                    'msr_count' => $c->manageStudentRecord->count(),
                    'msr_ids' => $c->manageStudentRecord->pluck('id')->toArray()
                ])->toArray(),
                'filters' => [
                    'course_id' => $courseId,
                    'subject_id' => $subjectId,
                    'topic_id' => $topicId,
                    'subtopic_id' => $subtopicId,
                    'attempt' => $attempt
                ]
            ]);

            // Step 2: Get past + current weeks assignments
            $currentDate = Carbon::now()->format('Y-m-d');
            $assignments = CourseAssignment::with(['weeks', 'manageStudentRecord', 'acdemicCourses.courses'])
                ->whereHas('manageStudentRecord', fn($q) => $q->whereIn('parent_id', $courseIds))
                ->whereHas('weeks', function($q) use ($currentDate) {
                    $q->where('start_date', '<=', $currentDate);
                })
                ->get();

            if ($assignments->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'No assignments found.',
                    'data' => []
                ], 200);
            }

            \Log::info('=== FLOW: Assignments Found ===', [
                'assignments_count' => $assignments->count(),
                'actual_assignment_ids' => $assignments->pluck('id')->toArray(),
                'assignments_details' => $assignments->map(fn($a) => [
                    'assignment_id' => $a->id,
                    'week_number' => $a->weeks ? $a->weeks->week_number : null,
                    'week_start_date' => $a->weeks ? $a->weeks->start_date : null,
                    'week_end_date' => $a->weeks ? $a->weeks->end_date : null,
                    'course_name' => $a->acdemicCourses && $a->acdemicCourses->courses ? $a->acdemicCourses->courses->name : 'Unknown',
                    'msr_count' => $a->manageStudentRecord->count(),
                    'msr_ids' => $a->manageStudentRecord->pluck('id')->toArray()
                ])->toArray(),
                'current_date' => $currentDate
            ]);

            // Step 3: Get ALL tests from these assignments (test-centric approach)
            $allTestsData = [];
            
            foreach ($assignments as $assignment) {
                $week = $assignment->weeks;
                $courseName = $assignment->acdemicCourses->courses->name ?? 'Unknown Course';
                
                \Log::info('=== FLOW: Processing Assignment ===', [
                    'assignment_id' => $assignment->id,
                    'week_number' => $week ? $week->week_number : null,
                    'week_start_date' => $week ? $week->start_date : null,
                    'week_end_date' => $week ? $week->end_date : null,
                    'course_name' => $courseName,
                    'has_weeks' => !is_null($week),
                    'has_acdemic_courses' => !is_null($assignment->acdemicCourses),
                    'has_courses' => $assignment->acdemicCourses ? !is_null($assignment->acdemicCourses->courses) : false
                ]);
                
                // Get assignment MSR IDs
                $assignmentMSRIds = $assignment->manageStudentRecord->pluck('id');
                
                \Log::info('=== FLOW: Assignment MSR IDs ===', [
                    'assignment_id' => $assignment->id,
                    'msr_ids' => $assignmentMSRIds->toArray(),
                    'msr_count' => $assignmentMSRIds->count()
                ]);
                
                // Get topics from this assignment
                $topicMSRs = ManageStudentRecord::whereIn('parent_id', $assignmentMSRIds)
                    ->where('model_type', 'App\\Models\\CourseTopic')
                    ->with('modelable.subject')
                    ->get();
                
                \Log::info('=== FLOW: Topic MSRs Found ===', [
                    'assignment_id' => $assignment->id,
                    'topic_msrs_count' => $topicMSRs->count(),
                    'topic_msrs_details' => $topicMSRs->map(fn($msr) => [
                        'msr_id' => $msr->id,
                        'parent_id' => $msr->parent_id,
                        'model_type' => $msr->model_type,
                        'model_id' => $msr->model_id,
                        'modelable_exists' => !is_null($msr->modelable),
                        'modelable_class' => $msr->modelable ? get_class($msr->modelable) : null
                    ])->toArray()
                ]);
                
                foreach ($topicMSRs as $topicMSR) {
                    $topic = $topicMSR->modelable;
                    
                    // Manual check if modelable is null
                    if (!$topic) {
                        \Log::warning('=== FLOW: Topic Modelable is NULL ===', [
                            'msr_id' => $topicMSR->id,
                            'model_type' => $topicMSR->model_type,
                            'model_id' => $topicMSR->model_id,
                            'trying_manual_load' => true
                        ]);
                        
                        // Try manual load
                        $topic = CourseTopic::find($topicMSR->model_id);
                        if ($topic) {
                            $topic->load('subject');
                        }
                        
                        \Log::info('=== FLOW: Manual Topic Load Result ===', [
                            'manual_topic_found' => !is_null($topic),
                            'topic_id' => $topic ? $topic->id : null,
                            'topic_name' => $topic ? $topic->name : null
                        ]);
                    }
                    
                    \Log::info('=== FLOW: Processing Topic ===', [
                        'assignment_id' => $assignment->id,
                        'topic_msr_id' => $topicMSR->id,
                        'topic_id' => $topic ? $topic->id : null,
                        'topic_name' => $topic ? $topic->name : null,
                        'has_subject' => $topic && $topic->subject ? true : false,
                        'subject_id' => $topic ? $topic->subject_id : null,
                        'subject_name' => $topic && $topic->subject ? $topic->subject->name : null
                    ]);
                    
                    // Get topic tests
                    $topicTests = CourseTest::whereHas('manageStudentRecord', fn($q) => $q->where('parent_id', $topicMSR->id))
                        ->whereNull('course_sub_topic_id')
                        ->get();
                    
                    \Log::info('=== FLOW: Topic Tests Found ===', [
                        'topic_id' => $topic ? $topic->id : null,
                        'topic_tests_count' => $topicTests->count(),
                        'topic_test_ids' => $topicTests->pluck('id')->toArray()
                    ]);
                    
                    foreach ($topicTests as $test) {
                        $allTestsData[] = [
                            'test' => $test,
                            'assignment' => $assignment,
                            'week' => $week,
                            'course_name' => $courseName,
                            'subject_name' => $topic && $topic->subject ? $topic->subject->name : null,
                            'topic_name' => $topic ? $topic->name : null,
                            'subtopic_name' => null,
                            'is_topic_test' => true
                        ];
                    }
                    
                    // Get subtopics from this topic
                    $subtopicMSRs = ManageStudentRecord::where('parent_id', $topicMSR->id)
                        ->where('model_type', 'App\\Models\\CourseSubTopic')
                        ->with('modelable')
                        ->get();
                    
                    \Log::info('=== FLOW: Subtopics Found ===', [
                        'topic_id' => $topic ? $topic->id : null,
                        'subtopic_msrs_count' => $subtopicMSRs->count(),
                        'subtopic_msr_ids' => $subtopicMSRs->pluck('id')->toArray()
                    ]);
                    
                    foreach ($subtopicMSRs as $subtopicMSR) {
                        $subtopic = $subtopicMSR->modelable;
                        
                        // Manual check if subtopic modelable is null
                        if (!$subtopic) {
                            \Log::warning('=== FLOW: Subtopic Modelable is NULL ===', [
                                'subtopic_msr_id' => $subtopicMSR->id,
                                'model_type' => $subtopicMSR->model_type,
                                'model_id' => $subtopicMSR->model_id,
                                'trying_manual_load' => true
                            ]);
                            
                            // Try manual load
                            $subtopic = CourseSubTopic::find($subtopicMSR->model_id);
                            
                            \Log::info('=== FLOW: Manual Subtopic Load Result ===', [
                                'manual_subtopic_found' => !is_null($subtopic),
                                'subtopic_id' => $subtopic ? $subtopic->id : null,
                                'subtopic_name' => $subtopic ? $subtopic->name : null
                            ]);
                        }
                        
                        \Log::info('=== FLOW: Processing Subtopic ===', [
                            'topic_id' => $topic ? $topic->id : null,
                            'subtopic_msr_id' => $subtopicMSR->id,
                            'model_type' => $subtopicMSR->model_type,
                            'model_id' => $subtopicMSR->model_id,
                            'subtopic_id' => $subtopic ? $subtopic->id : null,
                            'subtopic_name' => $subtopic ? $subtopic->name : null
                        ]);
                        
                        // Get subtopic tests
                        $subtopicTests = CourseTest::whereHas('manageStudentRecord', fn($q) => $q->where('parent_id', $subtopicMSR->id))
                            ->whereNotNull('course_sub_topic_id')
                            ->get();
                        
                        \Log::info('=== FLOW: Subtopic Tests Found ===', [
                            'subtopic_id' => $subtopic ? $subtopic->id : null,
                            'subtopic_tests_count' => $subtopicTests->count(),
                            'subtopic_test_ids' => $subtopicTests->pluck('id')->toArray()
                        ]);
                        
                        foreach ($subtopicTests as $test) {
                            $allTestsData[] = [
                                'test' => $test,
                                'assignment' => $assignment,
                                'week' => $week,
                                'course_name' => $courseName,
                                'subject_name' => $topic && $topic->subject ? $topic->subject->name : null,
                                'topic_name' => $topic ? $topic->name : null,
                                'subtopic_name' => $subtopic ? $subtopic->name : null,
                                'is_topic_test' => false
                            ];
                            \Log::info('283', [  'test' => $test,
                            'assignment' => $assignment,
                            'week' => $week,
                            'course_name' => $courseName,
                            'subject_name' => $topic && $topic->subject ? $topic->subject->name : null,
                            'topic_name' => $topic ? $topic->name : null,
                            'subtopic_name' => $subtopic ? $subtopic->name : null,
                            'is_topic_test' => false]);
                        }
                    }
                }
            }
            
            \Log::info('=== FLOW: All Tests Collected ===', [
                'total_tests' => count($allTestsData),
                'topic_tests' => count(array_filter($allTestsData, fn($t) => $t['is_topic_test'])),
                'subtopic_tests' => count(array_filter($allTestsData, fn($t) => !$t['is_topic_test']))
            ]);
            
            // Apply filters
            $originalCount = count($allTestsData);
            if ($subjectId || $topicId || $subtopicId) {
                $allTestsData = array_filter($allTestsData, function($testData) use ($subjectId, $topicId, $subtopicId) {
                    if ($subjectId && $testData['test']->courseTopic && $testData['test']->courseTopic->subject_id != $subjectId) {
                        return false;
                    }
                    if ($topicId && $testData['test']->course_topic_id != $topicId) {
                        return false;
                    }
                    if ($subtopicId && $testData['test']->course_sub_topic_id != $subtopicId) {
                        return false;
                    }
                    return true;
                });
            }
            
            \Log::info('=== FLOW: Filters Applied ===', [
                'original_count' => $originalCount,
                'filtered_count' => count($allTestsData),
                'filters_applied' => [
                    'subject_id' => $subjectId,
                    'topic_id' => $topicId,
                    'subtopic_id' => $subtopicId
                ]
            ]);
            
            // Step 4: Get user test answers for all tests
            $testIds = array_column($allTestsData, 'test');
            $testIds = collect($testIds)->pluck('id')->toArray();
            
            $userTestAnswers = UserTestAnswer::where('user_id', $userId)
                ->whereIn('test_id', $testIds)
                ->get()
                ->groupBy(['test_id', 'attempt_number']);
            
            \Log::info('=== FLOW: User Answers Found ===', [
                'test_ids_count' => count($testIds),
                'test_ids' => $testIds,
                'user_answers_count' => $userTestAnswers->count(),
                'answered_test_ids' => $userTestAnswers->keys()->toArray()
            ]);
            
            // Step 5: Build final response - test by test
            $finalResults = [];
            
            foreach ($allTestsData as $testData) {
                $test = $testData['test'];
                $week = $testData['week'];
                
                // Check if user attempted this test
                $userAttempts = $userTestAnswers->get($test->id, collect());
                
                if ($userAttempts->isNotEmpty()) {
                    // User attempted - add all attempts or filtered attempts
                    foreach ($userAttempts as $attemptNumber => $answers) {
                        $correctAnswers = $answers->where('is_correct', true)->count();
                        $totalQuestions = $answers->count();
                        $totalMarks = $test->total_marks ?? $totalQuestions;
                        $score = $totalQuestions > 0 ? round(($correctAnswers / $totalQuestions) * $totalMarks, 2) : 0;
                        
                        // Apply attempt filter
                        $shouldInclude = true;
                        $firstAttempt = $userAttempts->keys()->min();
                        $lastAttempt = $userAttempts->keys()->max();
                        
                        if ($attempt === 'first_attempt' && $attemptNumber != $firstAttempt) {
                            $shouldInclude = false;
                        } elseif ($attempt === 'last_attempt' && $attemptNumber != $lastAttempt) {
                            $shouldInclude = false;
                        }
                        
                        \Log::info('=== FLOW: Attempt Filtering Decision ===', [
                            'test_id' => $test->id,
                            'test_name' => $test->name,
                            'attempt_filter' => $attempt,
                            'current_attempt' => $attemptNumber,
                            'first_attempt' => $firstAttempt,
                            'last_attempt' => $lastAttempt,
                            'total_attempts' => $userAttempts->keys()->count(),
                            'should_include' => $shouldInclude,
                            'reason' => $shouldInclude ? 'Matches filter criteria' : 'Filtered out by attempt filter'
                        ]);
                        
                        if ($shouldInclude) {
                            $finalResults[] = [
                                'test_id' => $test->id,
                                'test_name' => $test->name,
                                'course_name' => $testData['course_name'],
                                'subject_name' => $testData['subject_name'],
                                'topic_name' => $testData['topic_name'],
                                'subtopic_name' => $testData['subtopic_name'],
                                'week_number' => $week->week_number,
                                'week_start_date' => $week->start_date,
                                'week_end_date' => $week->end_date,
                                'score' => $score,
                                'total_marks' => $totalMarks,
                                'total_questions' => $totalQuestions,
                                'correct_answers' => $correctAnswers,
                                'percentage' => round(($correctAnswers / $totalQuestions) * 100, 2),
                                'attempt_number' => $attemptNumber,
                                'completed_at' => $answers->first()->created_at,
                                'is_attempted' => true,
                                'is_completed' => true
                            ];
                        }
                    }
                } else {
                    // User did not attempt - add as not attempted
                    $finalResults[] = [
                        'test_id' => $test->id,
                        'test_name' => $test->name,
                        'course_name' => $testData['course_name'],
                        'subject_name' => $testData['subject_name'],
                        'topic_name' => $testData['topic_name'],
                        'subtopic_name' => $testData['subtopic_name'],
                        'week_number' => $week->week_number,
                        'week_start_date' => $week->start_date,
                        'week_end_date' => $week->end_date,
                        'score' => null,
                        'total_marks' => $test->total_marks,
                        'total_questions' => null,
                        'correct_answers' => null,
                        'percentage' => null,
                        'attempt_number' => 0,
                        'completed_at' => null,
                        'is_attempted' => false,
                        'is_completed' => false
                    ];
                }
            }
            
            \Log::info('=== FLOW: Final Results Built ===', [
                'total_results' => count($finalResults),
                'attempted_count' => count(array_filter($finalResults, fn($r) => $r['is_attempted'])),
                'not_attempted_count' => count(array_filter($finalResults, fn($r) => !$r['is_attempted'])),
                'attempt_filter_applied' => $attempt !== 'all',
                'attempt_filter_type' => $attempt,
                'attempt_breakdown' => [
                    'attempt_1' => count(array_filter($finalResults, fn($r) => $r['is_attempted'] && $r['attempt_number'] == 1)),
                    'attempt_2' => count(array_filter($finalResults, fn($r) => $r['is_attempted'] && $r['attempt_number'] == 2)),
                    'attempt_3' => count(array_filter($finalResults, fn($r) => $r['is_attempted'] && $r['attempt_number'] == 3)),
                    'attempt_4+' => count(array_filter($finalResults, fn($r) => $r['is_attempted'] && $r['attempt_number'] >= 4))
                ],
                'sample_result' => count($finalResults) > 0 ? [
                    'test_id' => $finalResults[0]['test_id'],
                    'test_name' => $finalResults[0]['test_name'],
                    'course_name' => $finalResults[0]['course_name'],
                    'subject_name' => $finalResults[0]['subject_name'],
                    'is_attempted' => $finalResults[0]['is_attempted'],
                    'attempt_number' => $finalResults[0]['attempt_number'] ?? 0
                ] : null
            ]);
            
            // Step 7: Get MSR data for tests (is_completed, overdue)
            $testIds = array_unique(array_column($finalResults, 'test_id'));
            $testMSRData = ManageStudentRecord::whereIn('model_id', $testIds)
                ->where('model_type', 'App\\Models\\CourseTest')
                ->get()
                ->keyBy('model_id');
            
            \Log::info('=== FLOW: Test MSR Data ===', [
                'test_ids' => $testIds,
                'msr_data_count' => $testMSRData->count(),
                'msr_data' => $testMSRData->map(fn($msr) => [
                    'test_id' => $msr->model_id,
                    'is_completed' => $msr->is_completed,
                    'is_overdue' => $msr->is_overdue,
                    'completed_at' => $msr->completed_at
                ])->toArray()
            ]);

            // Step 8: Group by weeks and then by test_id
            $weeklyResults = [];
            
            // First group by weeks
            $resultsByWeek = [];
            foreach ($finalResults as $result) {
                $weekKey = "Week " . $result['week_number'] . " (" . 
                          Carbon::parse($result['week_start_date'])->format('M d') . " - " . 
                          Carbon::parse($result['week_end_date'])->format('M d, Y') . ")";
                
                if (!isset($resultsByWeek[$weekKey])) {
                    $resultsByWeek[$weekKey] = [
                        'week_info' => [
                            'week_number' => $result['week_number'],
                            'start_date' => $result['week_start_date'],
                            'end_date' => $result['week_end_date'],
                            'status' => $this->getWeekStatus($result['week_start_date'], $result['week_end_date'])
                        ],
                        'results' => []
                    ];
                }
                
                $resultsByWeek[$weekKey]['results'][] = $result;
            }
            
            // Now group by test_id within each week
            foreach ($resultsByWeek as $weekKey => $weekData) {
                $weeklyResults[$weekKey] = [
                    'week_info' => $weekData['week_info'],
                    'tests' => []
                ];
                
                // Group results by test_id
                $testGroups = [];
                foreach ($weekData['results'] as $result) {
                    $testId = $result['test_id'];
                    if (!isset($testGroups[$testId])) {
                        $testGroups[$testId] = [];
                    }
                    $testGroups[$testId][] = $result;
                }
                
                // Create grouped test structure
                foreach ($testGroups as $testId => $attempts) {
                    // Get MSR data for this test
                    $testMSR = $testMSRData->get($testId);
                    
                    // Use first attempt for basic test info
                    $firstAttempt = $attempts[0];
                    
                    // Separate attempted and not attempted
                    $attemptedResults = array_filter($attempts, fn($a) => $a['is_attempted']);
                    $notAttemptedResult = array_filter($attempts, fn($a) => !$a['is_attempted']);
                    
                    if (!empty($attemptedResults)) {
                        // Test has attempts - create attempt history
                        $attemptHistory = [];
                        foreach ($attemptedResults as $attempt) {
                            $attemptHistory[] = [
                                'attempt_number' => $attempt['attempt_number'],
                                'score' => $attempt['score'],
                                'total_marks' => $attempt['total_marks'],
                                'total_questions' => $attempt['total_questions'],
                                'correct_answers' => $attempt['correct_answers'],
                                'percentage' => $attempt['percentage'],
                                'completed_at' => $attempt['completed_at']
                            ];
                        }
                        
                        // Get best attempt for main display
                        $bestAttempt = collect($attemptedResults)->sortByDesc('score')->first();
                        
                        $weeklyResults[$weekKey]['tests'][] = [
                            'test_id' => $testId,
                            'test_name' => $firstAttempt['test_name'],
                            'course_name' => $firstAttempt['course_name'],
                            'subject_name' => $firstAttempt['subject_name'],
                            'topic_name' => $firstAttempt['topic_name'],
                            'subtopic_name' => $firstAttempt['subtopic_name'],
                            'week_number' => $firstAttempt['week_number'],
                            'week_start_date' => $firstAttempt['week_start_date'],
                            'week_end_date' => $firstAttempt['week_end_date'],
                            // Best attempt data for main display
                            'best_score' => $bestAttempt['score'],
                            'total_marks' => $bestAttempt['total_marks'],
                            'best_percentage' => $bestAttempt['percentage'],
                            'total_attempts' => count($attemptedResults),
                            'last_attempt_at' => collect($attemptedResults)->sortByDesc('attempt_number')->first()['completed_at'],
                            // MSR data
                            'is_completed' => $testMSR ? (bool)$testMSR->is_completed : false,
                            'is_overdue' => $testMSR ? (bool)$testMSR->is_overdue : false,
                            'msr_completed_at' => $testMSR ? $testMSR->completed_at : null,
                            // Status
                            'is_attempted' => true,
                            'status' => $this->getTestStatus($bestAttempt['score'], $bestAttempt['total_marks']),
                            // Attempt history
                            'attempt_history' => $attemptHistory
                        ];
                    } else {
                        // Test not attempted
                        $notAttempted = $notAttemptedResult[0];
                        
                        $weeklyResults[$weekKey]['tests'][] = [
                            'test_id' => $testId,
                            'test_name' => $notAttempted['test_name'],
                            'course_name' => $notAttempted['course_name'],
                            'subject_name' => $notAttempted['subject_name'],
                            'topic_name' => $notAttempted['topic_name'],
                            'subtopic_name' => $notAttempted['subtopic_name'],
                            'week_number' => $notAttempted['week_number'],
                            'week_start_date' => $notAttempted['week_start_date'],
                            'week_end_date' => $notAttempted['week_end_date'],
                            // No attempt data
                            'best_score' => null,
                            'total_marks' => $notAttempted['total_marks'],
                            'best_percentage' => null,
                            'total_attempts' => 0,
                            'last_attempt_at' => null,
                            // MSR data
                            'is_completed' => $testMSR ? (bool)$testMSR->is_completed : false,
                            'is_overdue' => $testMSR ? (bool)$testMSR->is_overdue : false,
                            'msr_completed_at' => $testMSR ? $testMSR->completed_at : null,
                            // Status
                            'is_attempted' => false,
                            'status' => 'not_attempted',
                            // Empty attempt history
                            'attempt_history' => []
                        ];
                    }
                }
            }

            // Step 6: Calculate summary statistics (only for attempted tests)
            $attemptedResults = array_filter($finalResults, fn($r) => $r['is_attempted']);
            $totalTests = count($attemptedResults);
            $averageScore = $totalTests > 0 ? array_sum(array_column($attemptedResults, 'score')) / $totalTests : 0;
            $totalMarks = array_sum(array_column($attemptedResults, 'total_marks'));
            $totalScored = array_sum(array_column($attemptedResults, 'score'));

            // Step 9: Restructure for test-centric response with start_end_date
            $allTests = [];
            foreach ($weeklyResults as $weekKey => $weekData) {
                foreach ($weekData['tests'] as $test) {
                    // We already have the week data from assignment->weeks, so we can use start_end_date directly
                    // Since we loaded weeks relationship, we can access start_end_date
                    $startEndDate = Carbon::parse($weekData['week_info']['start_date'])->format('d M Y') . 
                                   ' to ' . 
                                   Carbon::parse($weekData['week_info']['end_date'])->format('d M Y');
                    
                    $allTests[] = array_merge($test, [
                        'week_key' => $weekKey,
                        'week_info' => array_merge($weekData['week_info'], [
                            'start_end_date' => $startEndDate
                        ])
                    ]);
                }
            }
            
            \Log::info('=== FLOW: Final Test-Centric Structure ===', [
                'total_unique_tests' => count($allTests),
                'sample_test' => count($allTests) > 0 ? [
                    'test_id' => $allTests[0]['test_id'],
                    'test_name' => $allTests[0]['test_name'],
                    'total_attempts' => $allTests[0]['total_attempts'],
                    'is_attempted' => $allTests[0]['is_attempted'],
                    'week_start_end_date' => $allTests[0]['week_info']['start_end_date']
                ] : null
            ]);

            $response = [
                'success' => true,
                'message' => 'Test results fetched successfully.',
                'data' => [
                    'tests' => $allTests,
                 
                ]
            ];

            return response()->json($response, 200);

        } catch (\Exception $e) {
            return errorLog("Failed to fetch test result: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
            
        }
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
     * Get test status based on score
     */
    private function getTestStatus($score, $totalMarks)
    {
        if ($totalMarks == 0) return 'unknown';
        
        $percentage = ($score / $totalMarks) * 100;
        
        if ($percentage >= 80) return 'excellent';
        if ($percentage >= 60) return 'good';
        if ($percentage >= 40) return 'average';
        return 'needs_improvement';
    }

    /**
     * Get total attempts for a specific test by user
     */
    private function getTotalAttempts($userId, $testId)
    {
        return UserTestAnswer::where('user_id', $userId)
            ->where('test_id', $testId)
            ->distinct('attempt_number')
            ->count('attempt_number');
    }

    /**
     * Get completion status based on completed_at timestamp
     */
    private function getCompletionStatus($completedAt)
    {
        if (!$completedAt) {
            return 'not_completed';
        }

        $completedDate = Carbon::parse($completedAt);
        $now = Carbon::now();
        
        $diffInHours = $now->diffInHours($completedDate);
        $diffInDays = $now->diffInDays($completedDate);
        
        if ($diffInHours < 1) {
            return 'just_completed';
        } elseif ($diffInHours < 24) {
            return 'completed_today';
        } elseif ($diffInDays < 7) {
            return 'completed_this_week';
        } else {
            return 'completed_earlier';
        }
    }
}
