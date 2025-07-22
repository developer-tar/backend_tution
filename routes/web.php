<?php

use Illuminate\Support\Facades\Route;
use App\Models\User;
Route::get('/', function () {
    return view('welcome');
});
Route::get('/users/{user}', function (User $user) {
    dd($user);
});