<?php

use App\Models\Course;
use App\Models\CourseAssignment;
use App\Models\CourseOption;
use App\Models\CourseQuestion;
use App\Models\CourseSubTopic;
use App\Models\CourseTest;
use App\Models\CourseTopic;
use App\Models\ManageStudentRecord;
use Illuminate\Support\Facades\Response;


/**
 * Success response method
 *
 * @param $result
 * @param $message
 * @return \Illuminate\Http\JsonResponse
 */

function sendResponse($result = 'delete', $message, $code = 200) {

    $response = [
        'success' => true,
        'data' => $result,
        'message' => $message,
    ];
    if ($response['data'] == 'delete')
        unset($repsonse['data']);
    return response()->json($response, $code);
    // return Response::json($response, $code);
}
/**
 * Return error response
 *
 * @param       $error
 * @param array $errorMessages
 * @param int   $code
 * @return \Illuminate\Http\JsonResponse
 */
function sendError($error, $errorMessages = [], $code = 404) {
    $response = [
        'success' => false,
        'message' => $error,
    ];

    !empty($errorMessages) ? $response['data'] = $errorMessages : null;
    return response()->json($response, $code);
}


function getFileType($extension) {
    return match ($extension) {
        'jpg', 'jpeg', 'png', 'gif' => config('constants.path.image'),
        'mp4', 'avi', 'mkv' => config('constants.path.video'),
        'pdf' => config('constants.path.pdf'),
        default => config('constants.path.others'),
    };
}
function createCourse($courseId, $userId) {
    return ManageStudentRecord::firstOrCreate([
        'course_id' => $courseId,
        'buyer_id' => $userId,
        'model_type' => Course::class,
        'model_id' => $courseId,
    ]);
}
function createAssignments(array $assignmentIds, $courseRecord) {
    $records = [];
    foreach (array_chunk($assignmentIds, 500) as $chunk) {
        foreach ($chunk as $assignmentId) {
            $record = ManageStudentRecord::firstOrCreate([
                'parent_id' => $courseRecord->id,
                'course_id' => $courseRecord->course_id,
                'buyer_id' => $courseRecord->buyer_id,
                'model_type' => CourseAssignment::class,
                'model_id' => $assignmentId,
            ]);
            if ($record) $records[] = $record;
        }
    }
    return $records;
}



function createTopics(array $topicIds, array $assignmentRecords, $courseRecord) {
    $records = [];

    // Preload and group topics by assignment ID
    $topics = CourseTopic::whereIn('id', $topicIds)
        ->get()
        ->groupBy('course_assignment_id');

    foreach ($assignmentRecords as $assignment) {
        $assignmentTopics = $topics[$assignment->model_id] ?? collect();

        foreach ($assignmentTopics->chunk(500) as $chunk) {
            foreach ($chunk as $topic) {
                $record = ManageStudentRecord::firstOrCreate([
                    'parent_id' => $assignment->id,
                    'course_id' => $courseRecord->course_id,
                    'buyer_id' => $courseRecord->buyer_id,
                    'model_type' => CourseTopic::class,
                    'model_id' => $topic->id,
                ]);

                $records[] = $record;
            }
        }
    }

    return $records;
}




function createSubTopics(array $subTopicIds, array $topicRecords, $courseRecord) {
    $records = [];

    // Correct: Load CourseSubTopic, not CourseTopic
    $subTopics = CourseSubTopic::whereIn('id', $subTopicIds)
        ->get()
        ->groupBy('course_topic_id');

    foreach ($topicRecords as $topic) {
        $assignSubTopics = $subTopics[$topic->model_id] ?? collect();

        foreach ($assignSubTopics->chunk(500) as $chunk) {
            foreach ($chunk as $subTopic) {
                $record = ManageStudentRecord::firstOrCreate([
                    'parent_id' => $topic->id,
                    'course_id' => $courseRecord->course_id,
                    'buyer_id' => $courseRecord->buyer_id,
                    'model_type' => CourseSubTopic::class,
                    'model_id' => $subTopic->id,
                ]);

                if ($record) $records[] = $record;
            }
        }
    }

    return $records;
}

function createTests(array $testIds, array $parentRecords, $courseRecord, $columnName) {
    $records = [];

    //fetch test
    $fetchTest = CourseTest::whereIn('id', $testIds)
        ->get()
        ->groupBy($columnName);

    foreach ($parentRecords as $parent) {
        $assignTest = $fetchTest[$parent->model_id] ?? collect();

        foreach ($assignTest->chunk(500) as $chunk) {
            foreach ($chunk as $test) {
                $record = ManageStudentRecord::firstOrCreate([
                    'parent_id' => $parent->id,
                    'course_id' => $courseRecord->course_id,
                    'buyer_id' => $courseRecord->buyer_id,
                    'model_type' => CourseTest::class,
                    'model_id' => $test->id,
                ]);

                if ($record) $records[] = $record;
            }
        }
    }

    return $records;
}


function createQuestions(array $questionIds, array $testRecords, $courseRecord) {
    $records = [];

    // Fetch questions
    $fetchQuestions = CourseQuestion::whereIn('id', $questionIds)
        ->get()
        ->groupBy('course_test_id');

    foreach ($testRecords as $test) {
        $assignedQuestions = $fetchQuestions[$test->model_id] ?? collect();

        foreach ($assignedQuestions->chunk(500) as $chunk) {
            foreach ($chunk as $question) {
                $record = ManageStudentRecord::firstOrCreate([
                    'parent_id' => $test->id,
                    'course_id' => $courseRecord->course_id,
                    'buyer_id' => $courseRecord->buyer_id,
                    'model_type' => CourseQuestion::class,
                    'model_id' => $question->id,
                ]);

                if ($record) $records[] = $record;
            }
        }
    }

    return $records;
}


function createOptions(array $optionIds, array $questionRecords, $courseRecord) {
    // Fetch the correct model — CourseOption, not CourseQuestion
    $fetchOptions = CourseOption::whereIn('id', $optionIds)
        ->get()
        ->groupBy('course_question_id');

    foreach ($questionRecords as $question) {
        $assignedOptions = $fetchOptions[$question->model_id] ?? collect();

        foreach ($assignedOptions->chunk(500) as $chunk) {
            foreach ($chunk as $option) {
                ManageStudentRecord::firstOrCreate([
                    'parent_id' => $question->id,
                    'course_id' => $courseRecord->course_id,
                    'buyer_id' => $courseRecord->buyer_id,
                    'model_type' => CourseOption::class,
                    'model_id' => $option->id,
                ]);
            }
        }
    }
}

