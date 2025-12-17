<?php

use App\Http\Controllers\Api\CommonWebhookController;
use App\Http\Controllers\Api\MockExamPurchaseController;
use App\Http\Controllers\Api\MockExamWebhookController;
use App\Http\Controllers\Api\PaperPurchaseController;
use App\Http\Controllers\Api\StripeController;
use App\Http\Controllers\Api\TestWebhookController;
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

Route::get('course/view', [FrontendController::class, 'courseView']);
//mock exam
Route::get('mock-exam/view', [FrontendController::class, 'mockExamView']);
Route::get('mock-exam/{slug}/details', [FrontendController::class, 'mockExamDetails']);
Route::get('mock-exam/categories', [FrontendController::class, 'mockExamCategories']);

// Paper public routes
Route::get('paper/view', [FrontendController::class, 'paperView']);
Route::get('paper/{slug}/details', [FrontendController::class, 'paperDetails']);
Route::get('paper/categories', [FrontendController::class, 'paperCategories']);

// Announcement public routes
Route::get('announcements', [FrontendController::class, 'announcementView']);
Route::get('announcements/{id}', [FrontendController::class, 'announcementDetails']);

// Classes and Modes APIs
Route::get('classes', [FrontendController::class, 'getActiveClasses']);
Route::get('modes', [FrontendController::class, 'getActiveModes']);

// Common webhook for both subscriptions and mock exam purchases
Route::post('stripe/webhook', [StripeController::class, 'handleWebhook']);

Route::get('{slug}', [FrontendController::class, 'courseViewBySlug']);
