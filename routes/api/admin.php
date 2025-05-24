<?php

use App\Http\Controllers\Api\Admin\Assignment;
use App\Http\Controllers\Api\Admin\AssignmentController;
use App\Http\Controllers\Api\Admin\CourseController;
use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UsersController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\FleetsController;

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
Route::resource('course', CourseController::class);
Route::resource('assignment', AssignmentController::class);
Route::get('course_acdemic_records', [AssignmentController::class, 'courseAcdemicRecords']);
Route::get('course_acdemic_based_weeks/{acdemic_course_id}', [AssignmentController::class, 'courseAcdemicBasedWeeks']);
