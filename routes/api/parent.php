<?php

use App\Http\Controllers\Api\MockExamPurchaseController;
use App\Http\Controllers\Api\PaperPurchaseController;
use App\Http\Controllers\Api\Parent\BillingInformationController;
use App\Http\Controllers\Api\Parent\ParentStudentCourseController;
use App\Http\Controllers\Api\Parent\RequestPaperToHomeController;
use App\Http\Controllers\Api\Parent\StudentController;
use App\Http\Controllers\Api\PaymentController;
use Illuminate\Support\Facades\Route;

Route::get('testing', function () {
    return 'Hello, welcome to parent api world';
});
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
// start student routing 
Route::get('students', [StudentController::class, 'index']);
Route::get('student-emails', [StudentController::class, 'getStudentEmails']);
Route::post('student/reset-password', [StudentController::class, 'resetPassword']);
Route::resource('student', StudentController::class)->only(['edit', 'update', 'destroy']);
Route::resource('add/student', StudentController::class);

//end student routing

// Parent-based course assignment routes
Route::get('students-with-courses', [ParentStudentCourseController::class, 'fetchStudentsWithAvailableCourses']);
Route::post('assign-course-to-student', [ParentStudentCourseController::class, 'assignCourseToStudent']);

//merge add to cart and update cart

Route::post('/checkout', [PaymentController::class, 'checkout']);

// Parent subscriptions
Route::get('subscriptions', [ParentStudentCourseController::class, 'getSubscriptions']);

// Paper checkout for parents
Route::post('paper/checkout', [PaperPurchaseController::class, 'parentCheckout']);

// Paper purchases for parents
Route::get('paper-purchases', [PaperPurchaseController::class, 'myPurchases']); // Get parent's purchased papers

// Billing Information Routes
Route::get('billing-information', [BillingInformationController::class, 'index']);
Route::post('billing-information', [BillingInformationController::class, 'store']);
Route::put('billing-information', [BillingInformationController::class, 'update']);
Route::delete('billing-information/{id}', [BillingInformationController::class, 'destroy']);

// Paper Request to Home Route
Route::post('request-paper-to-home', [RequestPaperToHomeController::class, 'store']);

//announcements routes
Route::get('announcements', [\App\Http\Controllers\Api\FrontendController::class, 'announcementView']);
Route::get('announcements/{id}', [\App\Http\Controllers\Api\FrontendController::class, 'announcementDetails']);
