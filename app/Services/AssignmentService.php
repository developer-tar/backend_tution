<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

class AssignmentService
{
    /**
     * Get subjects for current user
     */
    public function fetchSubjects(): array
    {
        $user = User::with([
            'course' => function ($q) {
                $q->whereNull('parent_id')->with('subjects');
            }
        ])->find(Auth::user()->id);

        if (!$user || $user->course->isEmpty()) {
            throw new \Exception('No courses found for this user.');
        }

        $subjects = collect();

        foreach ($user->course as $course) {
            $subjects = $subjects->merge(
                $course->subjects->map(function ($subject) {
                    return [
                        'id' => $subject->id,
                        'name' => $subject->name,
                    ];
                })
            );
        }

        return $subjects->values()->toArray();
    }

    /**
     * Get topic content with validation
     */
    public function getTopicContent(int $topicId): array
    {
        // Implementation for topic content
        // This would include week validation and content formatting
        
        return [
            'topic_id' => $topicId,
            'content' => 'Topic content here'
        ];
    }

    /**
     * Get sub-topic content with validation
     */
    public function getSubTopicContent(int $subTopicId): array
    {
        // Implementation for sub-topic content
        // This would include week validation and content formatting
        
        return [
            'sub_topic_id' => $subTopicId,
            'content' => 'Sub-topic content here'
        ];
    }
}
