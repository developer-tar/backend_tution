<?php

namespace App\Services;

use App\Models\CourseTest;
use Carbon\Carbon;

class TestAccessService
{
    /**
     * Get test data with access validation
     */
    public function getTestData(int $testId, string $testType = 'topic'): array
    {
        // Get test with relations
        $test = $this->getTestWithRelations($testId, $testType);
        
        if (!$test) {
            throw new \Exception('Test not found.');
        }

        // Validate week access
        $this->validateWeekAccess($test, $testType);

        // Format test data
        return $this->formatTestData($test);
    }

    /**
     * Get test with proper relations based on type
     */
    private function getTestWithRelations(int $testId, string $testType): ?CourseTest
    {
        $query = CourseTest::with(['question.options']);
        
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
                'You cannot access this test right now. Test is only available from ' . 
                Carbon::parse($week->start_date)->format('M d, Y') . ' to ' . 
                Carbon::parse($week->end_date)->format('M d, Y') . '.'
            );
        }
    }

    /**
     * Format test data for API response
     */
    private function formatTestData(CourseTest $test): array
    {
        $questions = $test->question->map(function ($question) {
            return [
                'id' => $question->id,
                'name' => $question->name,
                'duration_in_sec' => $question->duration_in_sec,
                'options' => $question->options->map(function ($option) {
                    return [
                        'id' => $option->id,
                        'name' => $option->name,
                    ];
                }),
            ];
        });

        return [
            'test' => [
                'id' => $test->id,
                'name' => $test->name,
                'questions' => $questions,
            ],
        ];
    }
}
