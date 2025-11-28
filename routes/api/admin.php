<?php


use App\Http\Controllers\Api\Admin\Assign\{AssignedStudentCourseController, AssignmentController, CourseContentTestController};

use App\Http\Controllers\Api\Admin\Assign\{CourseController, TopicSubTopicController, TimeSlotController};
use App\Http\Controllers\Api\Admin\Assign\MockExamController;
use App\Http\Controllers\Api\Admin\MasterFormController;
use App\Http\Controllers\Api\Admin\ParentController;
use App\Http\Controllers\Api\Admin\StudentController;
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
Route::patch('assign/course/{course}/toggle-status', [CourseController::class, 'toggleStatus']);

//end course routing

//start assign the course routing
Route::resource('assign/assignment', AssignmentController::class);
Route::patch('assign/assignment/{assignment}/toggle-status', [AssignmentController::class, 'toggleStatus']);
Route::get('ca_based_remaining_weeks/{acdemic_course_id}', [AssignmentController::class, 'courseAcdemicBasedRemainingWeeks']);
//end assign the course routing 

//start assigning the topic and subtopic based on the weeks routing
Route::get('ca_based_weeks_subjects/{acdemic_course_id}', [AssignmentController::class, 'courseAcdemicBasedWeeks']);
Route::resource('assign/topic/subtopic', TopicSubTopicController::class);
Route::get('assign/topic/subtopic/subtopic/{subtopic}', [TopicSubTopicController::class, 'showSubtopic']);
Route::put('assign/topic/subtopic/subtopic/{subtopic}', [TopicSubTopicController::class, 'updateSubtopic']);
Route::patch('assign/topic/subtopic/subtopic/{subtopic}', [TopicSubTopicController::class, 'updateSubtopic']);
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
Route::put('mock-exam/category/{id}', [MockExamController::class, 'updateCategory']);
Route::patch('mock-exam/category/{id}', [MockExamController::class, 'updateCategory']);
Route::delete('mock-exam/category/{id}', [MockExamController::class, 'deleteCategory']);
Route::resource('mock-exam', MockExamController::class);
Route::patch('mock-exam/{mockExam}/toggle-status', [MockExamController::class, 'toggleStatus']);
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
Route::get('master-form/all', [MasterFormController::class, 'getAll']); // Get all records for all entities
Route::get('master-form/{entity}', [MasterFormController::class, 'index']); // Get all records for entity
Route::post('master-form/{entity}', [MasterFormController::class, 'store']); // Create new record
Route::get('master-form/{entity}/{id}', [MasterFormController::class, 'show']); // Get specific record
Route::put('master-form/{entity}/{id}', [MasterFormController::class, 'update']); // Update record
Route::patch('master-form/{entity}/{id}', [MasterFormController::class, 'update']); // Update record (partial)
Route::delete('master-form/{entity}/{id}', [MasterFormController::class, 'destroy']); // Delete record
Route::post('master-form/{entity}/{id}/restore', [MasterFormController::class, 'restore']); // Restore soft-deleted record
//end master form CRUD operations

//start parent management routing
Route::get('parents', [ParentController::class, 'index']); // Get all parents
Route::get('parent/{parent}', [ParentController::class, 'show']); // Get parent details with students and courses
Route::get('parent/{parent}/subscriptions', [ParentController::class, 'getParentSubscriptions']); // Get parent subscriptions
Route::put('parent/{parent}', [ParentController::class, 'update']); // Update parent
Route::patch('parent/{parent}', [ParentController::class, 'update']); // Update parent (partial)
Route::delete('parent/{parent}', [ParentController::class, 'destroy']); // Delete parent and all students
//end parent management routing

//start student management routing
Route::get('students-with-courses', [StudentController::class, 'index']); // Get all students with assigned courses
Route::get('student/{student}', [StudentController::class, 'show']); // Get student details
Route::get('student/{student}/edit', [StudentController::class, 'show']); // Get student details for editing (same as show)
Route::put('student/{student}', [StudentController::class, 'update']); // Update student
Route::patch('student/{student}', [StudentController::class, 'update']); // Update student (partial)
Route::delete('student/{student}', [StudentController::class, 'destroy']); // Delete student
//end student management routing
