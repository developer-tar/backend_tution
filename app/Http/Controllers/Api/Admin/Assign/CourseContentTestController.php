<?php

namespace App\Http\Controllers\Api\Admin\Assign;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\StoreTopicSubTopicRequest;

use App\Models\CourseSubTopic;
use App\Models\CourseTopic;


use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
class CourseContentTestController extends Controller
{
    /**
     * Store a newly created resource in storage.
     *
     * @param  StoreTopicSubTopicRequest  $request
     * @return \Illuminate\Http\RedirectResponse
     */
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
