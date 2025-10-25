<?php

namespace App\Services;

use App\Models\CourseResultUser;
use App\Models\CourseTest;
use App\Models\ManageStudentRecord;
use App\Models\UserTestAnswer;
use Carbon\Carbon;

class TestSubmissionService
{
    /**
     * Submit test answers and calculate results
     */
    public function submitTest(int $userId, int $testId, array $answers, string $testType = 'topic'): array
    {
        // Get test with questions and correct answers
        $test = $this->getTestWithRelations($testId, $testType);
        
        if (!$test) {
            throw new \Exception('Test not found.');
        }

        // Check week-based access control
        $this->validateWeekAccess($test, $testType);

        $totalQuestions = $test->question->count();
        $correctAnswers = 0;
        $totalTimeTaken = 0;

        // Get attempt count
        $attemptCount = $this->getAttemptCount($userId, $testId);
        
        // Clear previous answers for this attempt
        $this->clearPreviousAnswers($userId, $testId, $attemptCount);

        // Process each answer
        foreach ($answers as $answer) {
            $result = $this->processAnswer($answer, $test, $userId, $testId, $attemptCount);
            
            if ($result['isCorrect']) {
                $correctAnswers++;
            }
            
            $totalTimeTaken += $result['timeTaken'];
        }

        // Calculate results
        $results = $this->calculateResults($totalQuestions, $correctAnswers, $totalTimeTaken, $attemptCount);
        
        // Store results
        $this->storeResults($userId, $testId, $results);
        
        // Update completion status if all answers correct
        if ($results['isCompleted']) {
            $this->markAsCompleted($userId, $testId);
        }

        return $results;
    }

    /**
     * Get test with proper relations based on type
     */
    private function getTestWithRelations(int $testId, string $testType): ?CourseTest
    {
        $query = CourseTest::with(['question.options', 'question.correctOptions']);
        
        if ($testType === 'topic') {
            return $query->whereNull('course_sub_topic_id')->find($testId);
        } else {
            return $query->whereNotNull('course_sub_topic_id')->find($testId);
        }
    }

    /**
     * Validate week-based access control
     */
    private function validateWeekAccess(CourseTest $test, string $testType): void
    {
        if ($testType === 'topic') {
            $week = optional($test->courseTopic->courseAssignment)->weeks;
        } else {
            $week = optional($test->courseSubTopic->courseTopic->courseAssignment)->weeks;
        }

        $now = Carbon::now();

        if (!$week) {
            throw new \Exception('Week information not found for this test.');
        }

        if (!$now->between(Carbon::parse($week->start_date), Carbon::parse($week->end_date))) {
            throw new \Exception(
                'You cannot submit this test right now. Test is only available from ' . 
                Carbon::parse($week->start_date)->format('M d, Y') . ' to ' . 
                Carbon::parse($week->end_date)->format('M d, Y') . '.'
            );
        }
    }

    /**
     * Get current attempt count
     */
    private function getAttemptCount(int $userId, int $testId): int
    {
        $existingResult = CourseResultUser::where([
            'student_id' => $userId,
            'test_id' => $testId
        ])->first();

        return $existingResult ? $existingResult->attempt_count + 1 : 1;
    }

    /**
     * Clear previous answers for current attempt
     */
    private function clearPreviousAnswers(int $userId, int $testId, int $attemptCount): void
    {
        UserTestAnswer::where([
            'user_id' => $userId,
            'test_id' => $testId,
            'attempt_number' => $attemptCount
        ])->delete();
    }

    /**
     * Process individual answer
     */
    private function processAnswer(array $answer, CourseTest $test, int $userId, int $testId, int $attemptCount): array
    {
        $questionId = $answer['question_id'];
        $selectedOptionId = $answer['option_id'];
        $timeTaken = $answer['time_taken'];

        // Find the question
        $question = $test->question->where('id', $questionId)->first();
        
        if (!$question) {
            return ['isCorrect' => false, 'timeTaken' => $timeTaken];
        }

        // Check if selected option is correct
        $isCorrect = $question->correctOptions->contains('id', $selectedOptionId);

        // Store user answer
        UserTestAnswer::create([
            'user_id' => $userId,
            'test_id' => $testId,
            'question_id' => $questionId,
            'selected_option_id' => $selectedOptionId,
            'time_taken_seconds' => $timeTaken,
            'is_correct' => $isCorrect,
            'attempt_number' => $attemptCount
        ]);

        return ['isCorrect' => $isCorrect, 'timeTaken' => $timeTaken];
    }

    /**
     * Calculate test results
     */
    private function calculateResults(int $totalQuestions, int $correctAnswers, int $totalTimeTaken, int $attemptCount): array
    {
        $scorePercentage = $totalQuestions > 0 ? round(($correctAnswers / $totalQuestions) * 100, 2) : 0;
        $isCompleted = ($correctAnswers == $totalQuestions);

        return [
            'score' => $scorePercentage,
            'correct_answers' => $correctAnswers,
            'total_questions' => $totalQuestions,
            'is_completed' => $isCompleted,
            'attempt_count' => $attemptCount,
            'total_time_taken' => $totalTimeTaken
        ];
    }

    /**
     * Store results in course_result_users table
     */
    private function storeResults(int $userId, int $testId, array $results): void
    {
        CourseResultUser::updateOrCreate(
            [
                'student_id' => $userId,
                'test_id' => $testId
            ],
            [
                'test_score' => $results['score'],
                'attempt_count' => $results['attempt_count']
            ]
        );
    }

    /**
     * Mark test as completed in manage_student_records
     */
    private function markAsCompleted(int $userId, int $testId): void
    {
        ManageStudentRecord::updateOrCreate(
            [
                'model_type' => 'App\\Models\\CourseTest',
                'model_id' => $testId,
                'buyer_id' => $userId
            ],
            [
                'is_completed' => config('constants.completed.YES'),
                'completed_at' => now()
            ]
        );
    }
}
