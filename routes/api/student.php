<?php
use App\Http\Controllers\Api\Parent\StudentController;
use App\Http\Controllers\Api\Student\AssignmentController;
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
Route::get('/current/assignment', [AssignmentController::class, 'currentAssignment']);

//end course routing

//topic content api with specfic id
Route::get('topic/content/view/{topic_id}', [AssignmentController::class, 'topicContentView']);

//topic test api with specfic id
Route::get('topic/test/{test_id}', [AssignmentController::class, 'topicTest']);

//subtopic content api with specfic id
Route::get('subtopic/content/view/{sub_topic_id}', [AssignmentController::class, 'subTopicContentView']);

