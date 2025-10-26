<?php
use App\Http\Controllers\Api\Admin\Assign\MockExamController;
use App\Http\Controllers\Api\Parent\StudentController;
use App\Http\Controllers\Api\Student\AssignmentController;
use App\Http\Controllers\Api\Student\TestResultController;
use App\Http\Controllers\Api\Student\ContentController;
use App\Http\Controllers\Api\Student\ViewedContentController;
use App\Http\Controllers\Api\Student\WeeklyPerformanceController;
use App\Http\Controllers\Api\Student\DashboardController;
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
Route::get('/current/assignment/stats', [AssignmentController::class, 'currentAssignmentStats']);

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

//get assigned subjects for user
Route::get('assigned/subjects', [AssignmentController::class, 'getAssignedSubjects']);

//mark content as completed
Route::post('mark/content/completed', [AssignmentController::class, 'markContentCompleted']);

//mock exam routes for students
Route::get('my-mock-exams', [MockExamController::class, 'myMockExams']);
Route::post('mock-exam/{mockExamId}/start', [MockExamController::class, 'startExam']);
Route::post('mock-exam/{purchaseId}/submit', [MockExamController::class, 'submitExam']);

//test results routes
Route::get('test-results/by-weeks', [TestResultController::class, 'getTestResultsByWeeks']);

//content routes
Route::get('content/by-weeks', [ContentController::class, 'getContentByWeeks']);

//viewed content routes
Route::get('viewed-content/by-weeks', [ViewedContentController::class, 'getViewedContentByWeeks']);

//weekly performance routes
Route::post('weekly-performance', [WeeklyPerformanceController::class, 'getWeeklyPerformance']);

//dashboard routes
Route::get('dashboard', [DashboardController::class, 'getDashboardData']);

//hierarchical data routes
Route::get('hierarchical-data', [AssignmentController::class, 'getHierarchicalData']);