<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\{CartController, AuthController, CommonDataController};

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


//cart api
Route::get('cart', [CartController::class, 'index']);
Route::post('cart/add', [CartController::class, 'add']);
Route::put('cart/update/{cart}', [CartController::class, 'update']);
Route::delete('cart/remove/{cart}', [CartController::class, 'remove']);
