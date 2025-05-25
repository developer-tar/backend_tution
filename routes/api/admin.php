<?php

use App\Http\Controllers\Api\Admin\Assignment;
use App\Http\Controllers\Api\Admin\AssignmentController;
use App\Http\Controllers\Api\Admin\CourseController;
use App\Http\Controllers\Api\Admin\TopicSubTopicController;
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
// start course routing 
Route::resource('assign/course', CourseController::class);

//end course routing

//start assign the course routing
Route::resource('assign/assignment', AssignmentController::class);
Route::get('ca_based_remaining_weeks/{acdemic_course_id}', [AssignmentController::class, 'courseAcdemicBasedRemainingWeeks']);
//end assign the course routing 

//start assigning the topic and subtopic based on the weeks routing
Route::get('ca_based_weeks_subjects/{acdemic_course_id}', [AssignmentController::class, 'courseAcdemicBasedWeeks']);
Route::resource('assign/topic/subtopic', TopicSubTopicController::class);
//end assigning the topic and subtopic based on the weeks routing


Route::get('ca_records', [AssignmentController::class, 'courseAcdemicRecords']);


