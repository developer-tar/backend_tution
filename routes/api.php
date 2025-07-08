<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\{CartController, AuthController, CommonDataController};
use App\Http\Controllers\Api\FrontendController;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;

//Testing api
Route::get('/testing', function () {
    return 'Hello,welcome to api world';
});

//login & register api for roles(tutor,parent,student)
Route::post('register', [AuthController::class, 'register']);

//login api & register  for admin role
Route::post('admin/login', [AuthController::class, 'AdminLogin']);


//common data 
Route::get('common/data', [CommonDataController::class, 'commonApi']);


//cart api
Route::middleware([
    EncryptCookies::class,
    AddQueuedCookiesToResponse::class,
    StartSession::class,
])->group(function () {
    Route::get('cart', [CartController::class, 'index']);
    Route::post('cart/add', [CartController::class, 'add']);
    Route::put('cart/update/{cart}', [CartController::class, 'update']);
    Route::delete('cart/remove/{cart}', [CartController::class, 'remove']);
    Route::post('login', [AuthController::class, 'login']);
});

//frontend api
Route::get('course/view', [FrontendController::class, 'courseView']);
Route::get('{slug}', [FrontendController::class, 'courseViewBySlug']);
