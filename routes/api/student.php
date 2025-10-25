<?php
use App\Http\Controllers\Api\Admin\Assign\MockExamController;
use App\Http\Controllers\Api\Parent\StudentController;
use App\Http\Controllers\Api\Student\AssignmentController;
use Illuminate\Support\Facades\Route;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/
// start course routing 
Route::get('/current/assignment', [AssignmentController::class, 'currentAssignment']);

//end course routing

//topic content api with specfic id
Route::get('topic/content/view/{topic_id}', [AssignmentController::class, 'topicContentView']);

//topic test api with specfic id
Route::get('topic/test/{test_id}', [AssignmentController::class, 'topicTest']);
Route::post('topic/test/{test_id}/submit', [AssignmentController::class, 'submitTopicTest']);

//subtopic content api with specfic id
Route::get('subtopic/content/view/{sub_topic_id}', [AssignmentController::class, 'subTopicContentView']);

//subtopic test api with specfic id
Route::get('subtopic/test/{sub_topic_test_id}', [AssignmentController::class, 'subTopicTest']);
Route::post('subtopic/test/{sub_topic_test_id}/submit', [AssignmentController::class, 'submitSubTopicTest']);

//fetch  subjects
Route::get('fetch/subjects', [AssignmentController::class, 'fetchSubjects']);

//mark content as completed
Route::post('mark/content/completed', [AssignmentController::class, 'markContentCompleted']);

//mock exam routes for students
Route::get('my-mock-exams', [MockExamController::class, 'myMockExams']);
Route::post('mock-exam/{mockExamId}/start', [MockExamController::class, 'startExam']);
Route::post('mock-exam/{purchaseId}/submit', [MockExamController::class, 'submitExam']);