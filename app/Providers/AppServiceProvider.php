<?php

namespace App\Providers;

use Illuminate\Support\Facades\Password;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        date_default_timezone_set('Europe/London');
        Passport::tokensCan([
            'Admin' => 'Administrator role',
            'Student' => 'Student role',
            'Tutor' => 'Tutor role',
            'Parent' => 'Parent role',
        ]);
        Passport::ignoreRoutes();
    }
}
