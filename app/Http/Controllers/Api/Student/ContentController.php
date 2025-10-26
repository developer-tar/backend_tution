<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\ContentRequest;
use App\Models\Course;
use App\Models\CourseAssignment;
use App\Models\CourseTopic;
use App\Models\CourseSubTopic;
use App\Models\ManageStudentRecord;
use App\Models\Week;
use Carbon\Carbon;

class ContentController extends Controller
{
    /**
     * Get all content by weeks with optional filtering
     */
    public function getContentByWeeks(ContentRequest $request)
    {
        try {
            $userId = auth()->id();
            
            // Get validated data from FormRequest
            $courseId = $request->course_id;
            $subjectId = $request->subject_id;
            $topicId = $request->topic_id;
            $subtopicId = $request->subtopic_id;
            $contentType = $request->content_type;
            $status = $request->status;
            
            \Log::info('=== FLOW: Content Type & Status Filter (Content) ===', [
                'content_type_param' => $contentType,
                'status_param' => $status,
                'filter_logic' => [
                    'content_type' => $contentType ? "Only {$contentType} content" : 'All content types',
                    'status' => $status ? "Only {$status} status" : 'All statuses'
                ]
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

            \Log::info('=== FLOW: Courses Found (Content) ===', [
                'user_id' => $userId,
                'courses_count' => $courses->count(),
                'actual_course_ids' => $courses->pluck('id')->toArray(),
                'course_msr_ids' => $courseIds->toArray(),
                'filters' => [
                    'course_id' => $courseId,
                    'subject_id' => $subjectId,
                    'topic_id' => $topicId,
                    'subtopic_id' => $subtopicId,
                    'content_type' => $contentType,
                    'status' => $status
                ]
            ]);

            // Step 2: Get assignments with past weeks + current week only (no future weeks)
            $currentDate = Carbon::now()->format('Y-m-d');
            $assignments = CourseAssignment::with(['weeks', 'manageStudentRecord', 'acdemicCourses.courses'])
                ->whereHas('manageStudentRecord', fn($q) => $q->whereIn('parent_id', $courseIds))
                ->whereHas('weeks', function($q) use ($currentDate) {
                    // Get weeks that have started (start_date <= current_date)
                    // This includes past weeks and current week, excludes future weeks
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

            \Log::info('=== FLOW: Assignments Found (Content) ===', [
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

            // Step 3: Get ALL content from these assignments using MSR approach
            $allContentData = [];
            
            foreach ($assignments as $assignment) {
                $week = $assignment->weeks;
                $courseName = $assignment->acdemicCourses->courses->name ?? 'Unknown Course';
                
                \Log::info('=== FLOW: Processing Assignment (Content) ===', [
                    'assignment_id' => $assignment->id,
                    'week_number' => $week ? $week->week_number : null,
                    'week_start_date' => $week ? $week->start_date : null,
                    'week_end_date' => $week ? $week->end_date : null,
                    'course_name' => $courseName
                ]);
                
                // Get assignment MSR IDs
                $assignmentMSRIds = $assignment->manageStudentRecord->pluck('id');
                
                // Get topics from this assignment
                $topicMSRs = ManageStudentRecord::whereIn('parent_id', $assignmentMSRIds)
                    ->where('model_type', 'App\\Models\\CourseTopic')
                    ->with('modelable.subject')
                    ->get();
                
                \Log::info('=== FLOW: Topic MSRs Found (Content) ===', [
                    'assignment_id' => $assignment->id,
                    'topic_msrs_count' => $topicMSRs->count(),
                    'topic_msr_ids' => $topicMSRs->pluck('id')->toArray()
                ]);
                
                foreach ($topicMSRs as $topicMSR) {
                    $topic = $topicMSR->modelable;
                    
                    // Manual check if modelable is null
                    if (!$topic) {
                        \Log::warning('=== FLOW: Topic Modelable is NULL (Content) ===', [
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
                    }
                    
                    if ($topic) {
                        $allContentData[] = [
                            'content' => $topic,
                            'content_type' => 'TopicContent',
                            'assignment' => $assignment,
                            'week' => $week,
                            'course_name' => $courseName,
                            'subject_name' => $topic && $topic->subject ? $topic->subject->name : null,
                            'topic_name' => $topic->name,
                            'subtopic_name' => null
                        ];
                    }
                    
                    // Get subtopics from this topic
                    $subtopicMSRs = ManageStudentRecord::where('parent_id', $topicMSR->id)
                        ->where('model_type', 'App\\Models\\CourseSubTopic')
                        ->with('modelable')
                        ->get();
                    
                    \Log::info('=== FLOW: Subtopics Found (Content) ===', [
                        'topic_id' => $topic ? $topic->id : null,
                        'subtopic_msrs_count' => $subtopicMSRs->count(),
                        'subtopic_msr_ids' => $subtopicMSRs->pluck('id')->toArray()
                    ]);
                    
                    foreach ($subtopicMSRs as $subtopicMSR) {
                        $subtopic = $subtopicMSR->modelable;
                        
                        // Manual check if subtopic modelable is null
                        if (!$subtopic) {
                            \Log::warning('=== FLOW: Subtopic Modelable is NULL (Content) ===', [
                                'subtopic_msr_id' => $subtopicMSR->id,
                                'model_type' => $subtopicMSR->model_type,
                                'model_id' => $subtopicMSR->model_id,
                                'trying_manual_load' => true
                            ]);
                            
                            // Try manual load
                            $subtopic = CourseSubTopic::find($subtopicMSR->model_id);
                        }
                        
                        if ($subtopic) {
                            $allContentData[] = [
                                'content' => $subtopic,
                                'content_type' => 'SubTopicContent',
                                'assignment' => $assignment,
                                'week' => $week,
                                'course_name' => $courseName,
                                'subject_name' => $topic && $topic->subject ? $topic->subject->name : null,
                                'topic_name' => $topic ? $topic->name : null,
                                'subtopic_name' => $subtopic->name
                            ];
                        }
                    }
                }
            }
            
            \Log::info('=== FLOW: All Content Collected (Content) ===', [
                'total_content' => count($allContentData),
                'topics' => count(array_filter($allContentData, fn($c) => $c['content_type'] === 'TopicContent')),
                'subtopics' => count(array_filter($allContentData, fn($c) => $c['content_type'] === 'SubTopicContent'))
            ]);

            // Apply filters
            $originalCount = count($allContentData);
            if ($subjectId || $topicId || $subtopicId || $contentType || $status) {
                $allContentData = array_filter($allContentData, function($contentData) use ($subjectId, $topicId, $subtopicId, $contentType, $status, $userId) {
                    $content = $contentData['content'];
                    
                    // Subject filter
                    if ($subjectId && $content->subject_id != $subjectId) {
                        return false;
                    }
                    
                    // Topic filter
                    if ($topicId) {
                        if ($contentData['content_type'] === 'TopicContent' && $content->id != $topicId) {
                            return false;
                        }
                        if ($contentData['content_type'] === 'SubTopicContent' && $content->course_topic_id != $topicId) {
                            return false;
                        }
                    }
                    
                    // Subtopic filter
                    if ($subtopicId) {
                        if ($contentData['content_type'] === 'TopicContent') {
                            return false; // No topics when filtering by subtopic
                        }
                        if ($contentData['content_type'] === 'SubTopicContent' && $content->id != $subtopicId) {
                            return false;
                        }
                    }
                    
                    // Content type filter
                    if ($contentType) {
                        // Assuming content has a 'type' field or we determine type from content_type
                        $actualContentType = $content->type ?? 'video'; // Default to video if not specified
                        if ($actualContentType !== $contentType) {
                            return false;
                        }
                    }
                    
                    // Status filter - check completion status from MSR
                    if ($status) {
                        // Get the MSR for this content to check completion status
                        $contentMSR = null;
                        if ($contentData['content_type'] === 'TopicContent') {
                            $contentMSR = ManageStudentRecord::where('model_type', 'App\\Models\\CourseTopic')
                                ->where('model_id', $content->id)
                                ->where('buyer_id', $userId)
                                ->first();
                        } elseif ($contentData['content_type'] === 'SubTopicContent') {
                            $contentMSR = ManageStudentRecord::where('model_type', 'App\\Models\\CourseSubTopic')
                                ->where('model_id', $content->id)
                                ->where('buyer_id', $userId)
                                ->first();
                        }
                        
                        $actualStatus = $this->getContentStatus($contentMSR);
                        if ($actualStatus !== $status) {
                            return false;
                        }
                    }
                    
                    return true;
                });
            }
            
            \Log::info('=== FLOW: Filters Applied (Content) ===', [
                'original_count' => $originalCount,
                'filtered_count' => count($allContentData),
                'filters_applied' => [
                    'subject_id' => $subjectId,
                    'topic_id' => $topicId,
                    'subtopic_id' => $subtopicId,
                    'content_type' => $contentType,
                    'status' => $status
                ]
            ]);

            // Step 4: Build final results
            $finalResults = [];
            
            foreach ($allContentData as $contentData) {
                $content = $contentData['content'];
                $week = $contentData['week'];
                
                // Get MSR for status
                $contentMSR = null;
                if ($contentData['content_type'] === 'TopicContent') {
                    $contentMSR = ManageStudentRecord::where('model_type', 'App\\Models\\CourseTopic')
                        ->where('model_id', $content->id)
                        ->where('buyer_id', $userId)
                        ->first();
                } elseif ($contentData['content_type'] === 'SubTopicContent') {
                    $contentMSR = ManageStudentRecord::where('model_type', 'App\\Models\\CourseSubTopic')
                        ->where('model_id', $content->id)
                        ->where('buyer_id', $userId)
                        ->first();
                }
                
                $finalResults[] = [
                    'content_type' => $contentData['content_type'],
                    'content_id' => $content->id,
                    'content_name' => $content->name,
                    'course_name' => $contentData['course_name'],
                    'subject_name' => $contentData['subject_name'],
                    'topic_name' => $contentData['topic_name'],
                    'subtopic_name' => $contentData['subtopic_name'],
                    'week_number' => $week->week_number,
                    'week_start_date' => $week->start_date,
                    'week_end_date' => $week->end_date,
                    'description' => $content->description ?? null,
                    'order' => $content->order ?? 0,
                    'type' => $content->type ?? 'video', // Content type (video, text, etc.)
                    'status' => $this->getContentStatus($contentMSR),
                    'created_at' => $content->created_at
                ];
            }
            
            \Log::info('=== FLOW: Final Results Built (Content) ===', [
                'total_results' => count($finalResults),
                'topics_count' => count(array_filter($finalResults, fn($r) => $r['content_type'] === 'TopicContent')),
                'subtopics_count' => count(array_filter($finalResults, fn($r) => $r['content_type'] === 'SubTopicContent')),
                'sample_result' => count($finalResults) > 0 ? [
                    'content_id' => $finalResults[0]['content_id'],
                    'content_name' => $finalResults[0]['content_name'],
                    'content_type' => $finalResults[0]['content_type']
                ] : null
            ]);

            // Step 5: Group by weeks for response
            $weeklyContent = [];
            foreach ($finalResults as $result) {
                $weekKey = "Week " . $result['week_number'] . " (" . 
                          Carbon::parse($result['week_start_date'])->format('M d') . " - " . 
                          Carbon::parse($result['week_end_date'])->format('M d, Y') . ")";
                
                if (!isset($weeklyContent[$weekKey])) {
                    $weeklyContent[$weekKey] = [
                        'week_info' => [
                            'week_number' => $result['week_number'],
                            'start_date' => $result['week_start_date'],
                            'end_date' => $result['week_end_date'],
                            'start_end_date' => Carbon::parse($result['week_start_date'])->format('d M Y') . 
                                               ' to ' . 
                                               Carbon::parse($result['week_end_date'])->format('d M Y'),
                            'status' => $this->getWeekStatus($result['week_start_date'], $result['week_end_date'])
                        ],
                        'content' => []
                    ];
                }
                
                $weeklyContent[$weekKey]['content'][] = $result;
            }
            
            // Sort content within each week by order
            foreach ($weeklyContent as &$weekData) {
                usort($weekData['content'], function($a, $b) {
                    return $a['order'] <=> $b['order'];
                });
            }

            // Step 6: Restructure for content-centric response with start_end_date
            $allContent = [];
            foreach ($weeklyContent as $weekKey => $weekData) {
                foreach ($weekData['content'] as $content) {
                    $allContent[] = array_merge($content, [
                        'week_key' => $weekKey,
                        'week_info' => $weekData['week_info']
                    ]);
                }
            }
            
            \Log::info('=== FLOW: Final Content-Centric Structure (Content) ===', [
                'total_unique_content' => count($allContent),
                'topics' => count(array_filter($allContent, fn($c) => $c['content_type'] === 'TopicContent')),
                'subtopics' => count(array_filter($allContent, fn($c) => $c['content_type'] === 'SubTopicContent')),
                'sample_content' => count($allContent) > 0 ? [
                    'content_id' => $allContent[0]['content_id'],
                    'content_name' => $allContent[0]['content_name'],
                    'content_type' => $allContent[0]['content_type'],
                    'week_start_end_date' => $allContent[0]['week_info']['start_end_date']
                ] : null
            ]);

            $response = [
                'success' => true,
                'message' => 'Content fetched successfully.',
                'data' => [
                    'content' => $allContent
                ]
            ];

            return response()->json($response, 200);

        } catch (\Exception $e) {
            return errorLog("Failed to fetch content: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
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
     * Get content status based on MSR completion
     */
    private function getContentStatus($contentMSR)
    {
        if (!$contentMSR) {
            return 'pending';
        }

        if ($contentMSR->is_completed == 1) {
            return 'completed';
        }

        // Check if it's overdue
        if ($contentMSR->is_overdue == 1) {
            return 'pending'; // Still pending but overdue
        }

        // If there's some progress but not completed
        if ($contentMSR->completed_at) {
            return 'in_progress';
        }

        return 'pending';
    }
}
