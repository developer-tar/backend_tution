<?php

use App\Http\Controllers\Api\CommonDataController;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;


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

