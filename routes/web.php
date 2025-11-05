<?php

use Illuminate\Support\Facades\Route;
use App\Models\User;
use App\Http\Controllers\Api\Admin\MasterFormTestController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/users/{user}', function (User $user) {
    dd($user);
});

// Master Form Test Interface Routes
Route::get('/master-form-test', [MasterFormTestController::class, 'index'])->name('master-form.test');
Route::post('/api/master-form-test/run-all', [MasterFormTestController::class, 'runAllTests'])->name('master-form.test.run-all');
Route::get('/api/master-form-test/cases', [MasterFormTestController::class, 'getTestCases'])->name('master-form.test.cases');