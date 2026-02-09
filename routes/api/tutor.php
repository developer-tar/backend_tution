<?php

use App\Http\Controllers\Api\ClassroomController;
use App\Http\Controllers\Api\Tutor\TutorDashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Tutor API Routes (auth:api + CheckToken::Tutor)
|--------------------------------------------------------------------------
*/

Route::get('dashboard', [TutorDashboardController::class, 'dashboard']);
Route::get('courses', [TutorDashboardController::class, 'courses']);
Route::get('students', [TutorDashboardController::class, 'students']);
Route::get('timeslots', [TutorDashboardController::class, 'timeSlots']);
Route::get('assignments', [TutorDashboardController::class, 'assignments']);
Route::get('tests', [TutorDashboardController::class, 'tests']);
Route::get('mock-exams', [TutorDashboardController::class, 'mockExams']);
Route::get('papers', [TutorDashboardController::class, 'papers']);
Route::get('announcements', [TutorDashboardController::class, 'announcements']);
Route::get('awards', [TutorDashboardController::class, 'awards']);
Route::get('certificates', [TutorDashboardController::class, 'certificates']);

// Classrooms (Jitsi Meet)
Route::get('classrooms', [ClassroomController::class, 'index']);
Route::post('classrooms', [ClassroomController::class, 'store']);
