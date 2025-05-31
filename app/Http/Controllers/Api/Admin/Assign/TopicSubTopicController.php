<?php

namespace App\Http\Controllers\Api\Admin\Assign;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\FetchTopicSubtopicList;
use App\Http\Requests\Api\Admin\StoreTopicSubTopicRequest;

use App\Models\CourseSubTopic;
use App\Models\CourseTopic;


use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
class TopicSubTopicController extends Controller
{
    /**
     * Store a newly created resource in storage.
     *
     * @param  StoreTopicSubTopicRequest  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function index(FetchTopicSubtopicList $request)
    {
        try {  // Pull filter vars once for clarity
            $academicCourseId = $request->input('acdemic_course_id');   // keep typo if DB says so
            $subjectId = $request->input('subject_id');
            $assignmentId = $request->input('assignment_id');
            $courseTopicId = $request->input('course_topic_id');
            $courseSubtopicId = $request->input('course_subtopic_id');

            $topics = CourseTopic::with([
                'subtopic:id,course_topic_id,name',
                'courseAssignment:id,week_id,acdemic_course_id',
                'courseAssignment.weeks:id,week_number,start_date,end_date',
                'courseAssignment.acdemicCourses:id,course_id,acdemic_id',
                'courseAssignment.acdemicCourses.courses:id,name',
                'courseAssignment.acdemicCourses.acdemicyears',
                'subject:id,name',
            ])
                ->when($academicCourseId, function ($q) use ($academicCourseId) {
                    $q->whereHas(
                        'courseAssignment.acdemicCourses',
                        fn($qq) => $qq->where('acdemic_course_id', $academicCourseId)
                    );
                })
                ->when($subjectId, fn($q) => $q->where('subject_id', $subjectId))
                ->when($assignmentId, function ($q) use ($assignmentId) {
                    $q->whereHas(
                        'courseAssignment',
                        fn($qq) => $qq->where('id', $assignmentId)
                    );
                })
                ->when($courseTopicId, fn($q) => $q->where('id', $courseTopicId))
                ->when($courseSubtopicId, function ($q) use ($courseSubtopicId) {
                    $q->whereHas(
                        'subtopic',
                        fn($qq) => $qq->where('id', $courseSubtopicId)
                    );
                })
                ->orderBy('created_at', 'desc')
                ->paginate(10)
                ->through(function ($item) {
                    return [
                        'acdemicyears' => optional($item->courseAssignment?->acdemicCourses?->acdemicyears)->start_end_year,
                        'course_name' => optional($item->courseAssignment?->acdemicCourses?->courses)->name,
                        'week_number' => optional($item->courseAssignment?->weeks)->week_number,
                        'week_name' => optional($item->courseAssignment?->weeks)->start_end_date,
                        'subject_name' => optional($item->subject)->name,
                        'topic_id' => $item->id,
                        'topic_name' => $item->name,
                        'topic_media' => $item->getMedia('content_upload')->map(fn($m) => [
                            'url' => $m->getUrl(),
                            'type' => $m->mime_type,
                        ]),
                        'subtopic' => $item->subtopic->map(fn($sub) => [
                            'id' => $sub->id,
                            'name' => $sub->name,
                            'media' => $sub->getMedia('content_upload')->map(fn($m) => [
                                'url' => $m->getUrl(),
                                'type' => $m->mime_type,
                            ]),
                        ]),
                    ];
                });

            $response = [
                'success' => true,
                'message' => $topics->total() ? 'Courses Topic/subTopic fetched successfully.'
                    : 'No record found.',
                'data' => $topics,
            ];

            return response()->json($response, 200);

        } catch (\Throwable $e) {
            Log::error("Failed to fetch topics: {$e->getMessage()} in {$e->getFile()}:{$e->getLine()}");
            return response()->json(
                ['message' => 'An error occurred while fetching topics and subtopics.'],
                500
            );
        }


    }
    public function store(StoreTopicSubTopicRequest $request)
    {
        try {
            DB::beginTransaction();

            $courseTopic = CourseTopic::firstOrCreate(
                [
                    'course_assignment_id' => $request->input('course_assigment_id'),
                    'subject_id' => $request->input('subject_id'),
                    'name' => $request->input('topic_name'),
                ]
            );

            $targetModel = $courseTopic;
            $isSubtopic = '';

            if ($request->filled('subtopic_name')) {
                $courseSubTopic = CourseSubTopic::firstOrCreate([
                    'course_topic_id' => $courseTopic->id,
                    'name' => $request->input('subtopic_name'),
                ]);

                $targetModel = $courseSubTopic;
                $isSubtopic = 'subtopic';
            }

            if ($request->hasFile('content_upload')) {
                // Check if any media exists already for this model in the 'content_upload' collection
                if ($targetModel->media()->where('collection_name', 'content_upload')->exists()) {
                    return response()->json(['message' => 'Content already uploaded for this topic/subtopic.'], 200);
                }

                foreach ($request->file('content_upload') as $file) {
                    $targetModel->addMedia($file)
                        ->toMediaCollection('content_upload', 'public');
                }
            }


            DB::commit();

            $response = [
                'success' => true,
                'message' => "Course topic  $isSubtopic created successfully",
            ];
            return response()->json($response, 200);


        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to create topic and subtopic for course. Message => {$e->getMessage()}, File => {$e->getFile()}, Line => {$e->getLine()}, Code => {$e->getCode()}.");
            $response = [
                'success' => false,
                'message' => "An error occurred during store",
            ];
            return response()->json($response, 500);
        }
    }
}
