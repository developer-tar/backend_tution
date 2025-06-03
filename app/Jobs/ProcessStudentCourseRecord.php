<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessStudentCourseRecord implements ShouldQueue {

    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    protected $courseId;
    protected $userId;
    protected $assignmentIds;
    protected $topicIds;
    protected $topicTestIds;
    protected $topicQuestionIds;
    protected $topicOptionIds;
    protected $subTopicIds;
    protected $subTopicTestIds;
    protected $subTopicQuestionIds;
    protected $subTopicOptionIds;

    public function __construct($courseId, $userId, $assignmentIds, $topicIds, $topicTestIds, $topicQuestionIds, $topicOptionIds, $subTopicIds, $subTopicTestIds, $subTopicQuestionIds, $subTopicOptionIds) {
        $this->courseId               = $courseId;
        $this->userId                 = $userId;
        $this->assignmentIds          = $assignmentIds;
        $this->topicIds               = $topicIds;
        $this->topicTestIds           = $topicTestIds;
        $this->topicQuestionIds       = $topicQuestionIds;
        $this->topicOptionIds         = $topicOptionIds;
        $this->subTopicIds            = $subTopicIds;
        $this->subTopicTestIds        = $subTopicTestIds;
        $this->subTopicQuestionIds    = $subTopicQuestionIds;
        $this->subTopicOptionIds      = $subTopicOptionIds;
    }

    public function handle() {
        $courseRecord = createCourse($this->courseId, $this->userId);

        $assignmentRecords = createAssignments($this->assignmentIds, $courseRecord);
        $topicRecords = createTopics($this->topicIds, $assignmentRecords, $courseRecord);
        $topicTestRecords = createTests($this->topicTestIds, $topicRecords, $courseRecord, 'course_topic_id');
        $topicQuestionRecords = createQuestions($this->topicQuestionIds, $topicTestRecords, $courseRecord);
        createOptions($this->topicOptionIds, $topicQuestionRecords, $courseRecord);

        $subtopicRecords = createSubTopics($this->subTopicIds, $topicRecords, $courseRecord);
        $subtopicTestRecords = createTests($this->subTopicTestIds, $subtopicRecords, $courseRecord, 'course_sub_topic_id');
        $subtopicQuestionRecords = createQuestions($this->subTopicQuestionIds, $subtopicTestRecords, $courseRecord);
        createOptions($this->subTopicOptionIds, $subtopicQuestionRecords, $courseRecord);
    }
}
