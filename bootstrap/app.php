<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Laravel\Passport\Http\Middleware\CheckToken;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Support\Facades\Route;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        api: __DIR__ . '/../routes/api.php',
        then: function () {
            Route::middleware(['auth:api', 'SubstituteBindings'])->group(function () {
                Route::middleware(CheckToken::using('Admin'))->prefix('api/admin')->group(base_path('routes/api/admin.php'));
                Route::middleware(CheckToken::using('Tutor'))->prefix('api/tutor')->group(base_path('routes/api/tutor.php'));
                Route::middleware(CheckToken::using('Student'))->prefix('api/student')->group(base_path('routes/api/student.php'));
                Route::middleware(CheckToken::using('Parent'))->prefix('api/parent')->group(base_path('routes/api/parent.php'));
            });
        },
    )

    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'SubstituteBindings' => SubstituteBindings::class
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (NotFoundHttpException $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Resource not found.',
            ], 404);
        });
    })

    ->create();
