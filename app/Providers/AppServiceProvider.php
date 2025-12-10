<?php

namespace App\Providers;

use App\Models\MockExam;
use App\Models\Paper;
use App\Policies\MockExamPolicy;
use App\Policies\PaperPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        MockExam::class => MockExamPolicy::class,
        Paper::class => PaperPolicy::class,
    ];

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
