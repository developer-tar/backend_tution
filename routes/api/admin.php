<?php


use App\Http\Controllers\Api\Admin\Assign\{AssignedStudentCourseController, AssignmentController, CourseContentTestController};

use App\Http\Controllers\Api\Admin\Assign\{CourseController, TopicSubTopicController, TimeSlotController};
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
