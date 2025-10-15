<?php

use App\Http\Controllers\Api\MockExamPurchaseController;
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
// start course routing 
Route::resource('add/student', StudentController::class);

//end course routing

//merge add to cart and update cart

Route::post('/checkout', [PaymentController::class, 'checkout']);

// Mock exam purchase for parents
// Route::post('/mock-exam-checkout', [MockExamPurchaseController::class, 'parentCheckout']);
// Route::post('/mock-exam/verify-payment', [MockExamPurchaseController::class, 'verifyPayment']);
// Route::get('/my-mock-exam-purchases', [MockExamPurchaseController::class, 'myPurchases']);