<?php


use App\Http\Controllers\Api\Admin\Assign\{AssignedStudentCourseController, AssignmentController, CourseContentTestController};

use App\Http\Controllers\Api\Admin\Assign\{CourseController, TopicSubTopicController, TimeSlotController};
use App\Http\Controllers\Api\Admin\Assign\MockExamController;
use App\Http\Controllers\Api\Admin\Assign\PaperController;
use App\Http\Controllers\Api\Admin\AnnouncementController;
use App\Http\Controllers\Api\Admin\AwardController;
use App\Http\Controllers\Api\Admin\CertificateController;
use App\Http\Controllers\Api\Admin\MasterFormController;
use App\Http\Controllers\Api\Admin\ParentController;
use App\Http\Controllers\Api\Admin\PaperPurchaseController;
use App\Http\Controllers\Api\Admin\PaperExtractController;
use App\Http\Controllers\Api\Admin\StudentController;
use App\Http\Controllers\Api\Admin\CourseInstallmentController;
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

// Course installment management routes
Route::get('assign/course/{course}/installments', [CourseInstallmentController::class, 'index']);
Route::post('assign/course/{course}/installments', [CourseInstallmentController::class, 'store']);
Route::get('assign/course/{course}/installments/{installment}', [CourseInstallmentController::class, 'show']);
Route::put('assign/course/{course}/installments/{installment}', [CourseInstallmentController::class, 'update']);
Route::patch('assign/course/{course}/installments/{installment}', [CourseInstallmentController::class, 'update']);
Route::delete('assign/course/{course}/installments/{installment}', [CourseInstallmentController::class, 'destroy']);

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

// Paper category routes (uses MockExamCategory - shared with mock exams)
Route::get('paper/categories', [PaperController::class, 'getCategories']);
Route::get('paper/category-tree', [PaperController::class, 'getCategoryTree']);
Route::post('paper/category', [PaperController::class, 'storeCategory']);
Route::put('paper/category/{id}', [PaperController::class, 'updateCategory']);
Route::patch('paper/category/{id}', [PaperController::class, 'updateCategory']);
Route::delete('paper/category/{id}', [PaperController::class, 'deleteCategory']);

// Paper CRUD routes
Route::resource('paper', PaperController::class);
Route::patch('paper/{paper}/toggle-status', [PaperController::class, 'toggleStatus']);

// Paper Extract routes
Route::resource('paper-extracts', PaperExtractController::class);
Route::get('paper-extracts/{id}/questions/search', [PaperExtractController::class, 'searchQuestions']);
Route::get('paper-extracts/statistics/overview', [PaperExtractController::class, 'statistics']);
Route::put('paper-extracts/questions/{id}', [PaperExtractController::class, 'updateQuestion']);
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

//start paper purchase management routing
Route::get('paper-purchases', [PaperPurchaseController::class, 'index']); // Get aggregated paper purchases list
//end paper purchase management routing

//start announcement management routing
Route::get('announcements/module-modes', [AnnouncementController::class, 'getModuleModes']); // Get module modes
Route::get('announcements/academic-years', [AnnouncementController::class, 'getAcademicYears']); // Get academic years
Route::get('announcements/filtered-items', [AnnouncementController::class, 'getFilteredItems']); // Get courses/papers/mock exams filtered by mode and academic year
Route::get('announcements/classes', [AnnouncementController::class, 'getClasses']); // Get classes (timeslots) for a course
Route::resource('announcements', AnnouncementController::class);
//end announcement management routing

//start awards and certificates management routing
Route::resource('awards', AwardController::class);
// Specific certificate routes must be defined BEFORE resource route to avoid route conflicts
Route::get('certificates/academic-years', [CertificateController::class, 'getAcademicYears']); // Get academic years
Route::get('certificates/courses-by-year', [CertificateController::class, 'getCoursesByYear']); // Get courses by academic year
Route::get('certificates/modes', [CertificateController::class, 'getModes']); // Get modes (online/physical)
Route::get('certificates/students/list', [CertificateController::class, 'getStudents']); // Get students for certificate generation
Route::get('certificates/students/filtered', [CertificateController::class, 'getFilteredStudents']); // Get filtered students
Route::get('certificates/{id}/download', [CertificateController::class, 'download']); // Download certificate PDF
Route::patch('certificates/{id}/revoke', [CertificateController::class, 'revoke']); // Revoke certificate
Route::resource('certificates', CertificateController::class);
//end awards and certificates management routing
