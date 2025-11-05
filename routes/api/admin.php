<?php


use App\Http\Controllers\Api\Admin\Assign\{AssignedStudentCourseController, AssignmentController, CourseContentTestController};

use App\Http\Controllers\Api\Admin\Assign\{CourseController, TopicSubTopicController, TimeSlotController};
use App\Http\Controllers\Api\Admin\Assign\MockExamController;
use App\Http\Controllers\Api\Admin\MasterFormController;
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


//start assigning the test for course content routing. 
Route::resource('assign/test', CourseContentTestController::class);
Route::get('fetch/course/topic/{subject_id}/{course_assignment_id}', [CourseContentTestController::class, 'fetchTopic']);
Route::get('fetch/course/subtopic/{topic_id}', [CourseContentTestController::class, 'fetchSubTopic']);
//end assigning the test for course content routing.

//start mock exam routing
Route::get('mock-exam/categories', [MockExamController::class, 'getCategories']);
Route::get('mock-exam/category-tree', [MockExamController::class, 'getCategoryTree']);
Route::post('mock-exam/category', [MockExamController::class, 'storeCategory']);
Route::resource('mock-exam', MockExamController::class);
//end mock exam routing

Route::get('ca_records', [AssignmentController::class, 'courseAcdemicRecords']);

//start assigning the student for course  routing.
Route::get('fetch/student/list/{acdemic_course_id}', [AssignedStudentCourseController::class, 'fetchStudentList']);
Route::post('assign/course/student', [AssignedStudentCourseController::class, 'store']);


//location based timeslot to the course
Route::get('course/timeslot', [TimeSlotController::class, 'index']);
Route::get('timeslot/{timeslot}', [TimeSlotController::class, 'show']);
Route::post('timeslot', [TimeSlotController::class, 'save']);
Route::patch('timeslot', [TimeSlotController::class, 'update']);
Route::delete('timeslot/{timeslot}', [TimeSlotController::class, 'destroy']);
Route::get('ca_based_location/{ca}', [TimeSlotController::class, 'getLocation']); //get the location based on acdemic course
Route::get('course/location/timeslot/{academic_course_id}/{location_id}/{weekday_id}', [TimeSlotController::class, 'getTimeSlot']); //for fetch the data in saving the data 

//start master form CRUD operations
Route::get('master-form/entities', [MasterFormController::class, 'getEntities']); // Get available entities
Route::get('master-form/{entity}', [MasterFormController::class, 'index']); // Get all records for entity
Route::post('master-form/{entity}', [MasterFormController::class, 'store']); // Create new record
Route::get('master-form/{entity}/{id}', [MasterFormController::class, 'show']); // Get specific record
Route::put('master-form/{entity}/{id}', [MasterFormController::class, 'update']); // Update record
Route::patch('master-form/{entity}/{id}', [MasterFormController::class, 'update']); // Update record (partial)
Route::delete('master-form/{entity}/{id}', [MasterFormController::class, 'destroy']); // Delete record
Route::post('master-form/{entity}/{id}/restore', [MasterFormController::class, 'restore']); // Restore soft-deleted record
//end master form CRUD operations
