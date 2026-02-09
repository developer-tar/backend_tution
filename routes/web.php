<?php

use Illuminate\Support\Facades\Route;
use App\Models\User;
use App\Http\Controllers\Api\Admin\MasterFormTestController;
use App\Http\Controllers\Api\ClassroomController;

Route::get('/', function () {
    return view('welcome');
});

// Test route to verify routing works
Route::get('/test-swagger-route', function () {
    return response()->json([
        'success' => true,
        'message' => 'Route is working!',
        'swagger_file_exists' => file_exists(base_path('swagger/complete-api-documentation.yaml')),
        'view_file_exists' => file_exists(resource_path('views/swagger-ui.blade.php')),
    ]);
});

Route::get('/users/{user}', function (User $user) {
    dd($user);
});

// Classroom live (Jitsi Meet) – requires auth for display name
Route::get('classroom/live/{roomCode}', [ClassroomController::class, 'live'])
    ->middleware('auth')
    ->name('classroom.live');

// Master Form Test Interface Routes
Route::get('/master-form-test', [MasterFormTestController::class, 'index'])->name('master-form.test');
Route::post('/api/master-form-test/run-all', [MasterFormTestController::class, 'runAllTests'])->name('master-form.test.run-all');
Route::get('/api/master-form-test/cases', [MasterFormTestController::class, 'getTestCases'])->name('master-form.test.cases');

// Swagger Documentation Routes (must be before any catch-all routes)
Route::get('/api-docs', function () {
    $swaggerFile = base_path('swagger/complete-api-documentation.yaml');
    
    if (!file_exists($swaggerFile)) {
        return response('Swagger documentation file not found.', 404);
    }
    
    // Return Swagger UI HTML
    return view('swagger-ui');
})->name('swagger.docs');

// Swagger YAML file endpoint
Route::get('/api-docs.yaml', function () {
    $swaggerFile = base_path('swagger/complete-api-documentation.yaml');
    
    if (!file_exists($swaggerFile)) {
        return response('Swagger documentation file not found.', 404);
    }
    
    return response()->file($swaggerFile, [
        'Content-Type' => 'application/x-yaml',
    ]);
})->name('swagger.yaml');
