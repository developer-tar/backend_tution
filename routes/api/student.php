<?php

use App\Http\Controllers\Api\Admin\Assign\MockExamController;
use App\Http\Controllers\Api\Admin\Assign\PaperController;
use App\Http\Controllers\Api\CoursePurchaseController;
use App\Http\Controllers\Api\PaperPurchaseController;
use App\Http\Controllers\Api\Parent\StudentController;
use App\Http\Controllers\Api\FrontendController;
use App\Http\Controllers\Api\Student\AssignmentController;
use App\Http\Controllers\Api\Student\TestResultController;
use App\Http\Controllers\Api\Student\ContentController;
use App\Http\Controllers\Api\Student\ViewedContentController;
use App\Http\Controllers\Api\Student\WeeklyPerformanceController;
use App\Http\Controllers\Api\Student\DashboardController;
use App\Http\Controllers\Api\Student\CertificateController;
use App\Http\Controllers\Api\NotificationController;
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

// Paper routes
Route::get('my-papers', [PaperController::class, 'myPapers']);
Route::post('paper/{paperId}/start', [PaperController::class, 'startPaper']);
Route::post('paper/{purchaseId}/submit', [PaperController::class, 'submitPaper']);
Route::post('paper/purchase', [PaperPurchaseController::class, 'purchasePaper']);
Route::get('paper/my-purchases', [PaperPurchaseController::class, 'myPurchases']);
Route::post('paper/verify-payment', [PaperPurchaseController::class, 'verifyPayment']);

// Course routes
Route::post('course/purchase', [CoursePurchaseController::class, 'purchaseCourse']);

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
Route::post('dashboard/subject', [DashboardController::class, 'getSubjectDashboardData']);

//announcements routes
Route::get('announcements', [FrontendController::class, 'getAnnouncements']);

//notifications routes
Route::get('notifications', [NotificationController::class, 'index']);
Route::get('notifications/unread-count', [NotificationController::class, 'getUnreadCount']);
Route::post('notifications/{id}/mark-as-read', [NotificationController::class, 'markAsRead']);
Route::post('notifications/mark-all-as-read', [NotificationController::class, 'markAllAsRead']);

//certificates routes
Route::get('certificates', [CertificateController::class, 'index']);
Route::get('certificates/{id}', [CertificateController::class, 'show']);
Route::get('certificates/{id}/download', [CertificateController::class, 'download']);

//hierarchical data routes
Route::get('hierarchical-data', [AssignmentController::class, 'getHierarchicalData']);

// Change password route
use App\Http\Controllers\Api\AuthController;

Route::post('change-password', [AuthController::class, 'changePassword']);
