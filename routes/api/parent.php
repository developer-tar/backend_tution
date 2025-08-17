<?php

use App\Http\Controllers\Api\Parent\StudentController;
use App\Http\Controllers\Api\PaymentController;
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
Route::resource('add/student', StudentController::class);

//end course routing

//merge add to cart and update cart

Route::post('/subscription-checkout', [PaymentController::class, 'subscriptionCheckout']);