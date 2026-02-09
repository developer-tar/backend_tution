<?php

use App\Http\Controllers\Api\CoursePurchaseController;
use App\Http\Controllers\Api\FrontendController;
use App\Http\Controllers\Api\MockExamPurchaseController;
use App\Http\Controllers\Api\PaperPurchaseController;
use App\Http\Controllers\Api\Parent\BillingInformationController;
use App\Http\Controllers\Api\Parent\ParentStudentCourseController;
use App\Http\Controllers\Api\Parent\RequestPaperToHomeController;
use App\Http\Controllers\Api\Parent\StudentController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\NotificationController;
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
Route::get('students/names', [StudentController::class, 'namesForDropdown']); // student_details (parent_id = logged parent) -> child_id names for dropdowns
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
Route::post('/verify-payment', [PaymentController::class, 'verifyPayment']);
Route::post('/checkout/fulfill', [PaymentController::class, 'fulfill']);

// Parent subscriptions
Route::get('subscriptions', [ParentStudentCourseController::class, 'getSubscriptions']);
Route::get('subscribed-course-ids', [ParentStudentCourseController::class, 'getSubscribedCourseIds']);

// Paper checkout for parents
Route::post('paper/checkout', [PaperPurchaseController::class, 'parentCheckout']);

// Mock exam checkout for parents
Route::post('mock-exam/checkout', [MockExamPurchaseController::class, 'parentCheckout']);

// Course checkout for parents
Route::post('course/checkout', [CoursePurchaseController::class, 'parentCheckout']);

// Course registration fee and installment routes
Route::post('course/registration-fee/pay', [CoursePurchaseController::class, 'payRegistrationFee']);
Route::get('course/registration-fee/status', [CoursePurchaseController::class, 'checkRegistrationFeeStatus']);
Route::get('course/installments', [CoursePurchaseController::class, 'getInstallments']);
Route::post('course/installment/pay', [CoursePurchaseController::class, 'payInstallment']);
Route::get('course/payment-status', [CoursePurchaseController::class, 'getPaymentStatus']);

// Paper purchases for parents
Route::get('paper-purchases', [PaperPurchaseController::class, 'myPurchases']); // Get parent's purchased papers
Route::put('paper-purchases/{purchaseId}/assign-student', [PaperPurchaseController::class, 'assignStudent']);

// Billing Information Routes
Route::get('billing-information', [BillingInformationController::class, 'index']);
Route::post('billing-information', [BillingInformationController::class, 'store']);
Route::put('billing-information', [BillingInformationController::class, 'update']);
Route::delete('billing-information/{id}', [BillingInformationController::class, 'destroy']);

// Paper Request to Home Route
Route::post('request-paper-to-home', [RequestPaperToHomeController::class, 'store']);

// Announcements routes
Route::get('announcements', [FrontendController::class, 'getAnnouncements']);

// Notifications routes
Route::get('notifications', [NotificationController::class, 'index']);
Route::get('notifications/unread-count', [NotificationController::class, 'getUnreadCount']);
Route::post('notifications/{id}/mark-as-read', [NotificationController::class, 'markAsRead']);
Route::post('notifications/mark-all-as-read', [NotificationController::class, 'markAllAsRead']);

// Certificates routes (for viewing student certificates)
use App\Http\Controllers\Api\Parent\CertificateController;

Route::get('certificates', [CertificateController::class, 'index']); // Get certificates for parent's students
Route::get('certificates/{id}', [CertificateController::class, 'show']);
Route::get('certificates/{id}/download', [CertificateController::class, 'download']);

// Change password route
use App\Http\Controllers\Api\AuthController;

Route::post('change-password', [AuthController::class, 'changePassword']);
