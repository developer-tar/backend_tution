<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        api: __DIR__.'/../routes/api.php',
        then: function () {
            Route::middleware('auth:api')->group(function () {
                Route::prefix('api/admin')->group(base_path('routes/api/admin.php'));
                Route::prefix('api/tutor')->group(base_path('routes/api/tutor.php'));
                Route::prefix('api/student')->group(base_path('routes/api/student.php'));
                Route::prefix('api/parent')->group(base_path('routes/api/parent.php'));
            });
        },

    )
    ->withMiddleware(function (Middleware $middleware) {
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (AuthenticationException $e, $request) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        });

    })->create();
