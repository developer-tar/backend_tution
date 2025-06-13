<?php

use App\Http\Controllers\Api\CommonDataController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\FrontendController;

//Testing api
Route::get('/testing', function () {
    return 'Hello,welcome to api world';
});

//login & register api for roles(tutor,parent,student)
Route::post('login', [AuthController::class, 'login']);
Route::post('register', [AuthController::class, 'register']);

//login api & register  for admin role
Route::post('admin/login', [AuthController::class, 'AdminLogin']);


//common data 
Route::get('common/data', [CommonDataController::class, 'commonApi']);

Route::get('course/view', [FrontendController::class, 'courseView']);

Route::get('{slug}', [FrontendController::class, 'courseViewBySlug']);