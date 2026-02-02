<?php

namespace App\Console\Commands;

use App\Jobs\CreateStripePrice;
use App\Models\Course;
use App\Models\CoursePrice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;

class CreateStripePrices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stripe:create-prices 
                            {--course= : The course ID to create prices for}
                            {--all : Create prices for all courses}
                            {--missing : Create prices only for courses missing Stripe price IDs}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create Stripe price IDs for courses';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $stripeSecret = config('services.stripe.sk_test');

        if (!$stripeSecret) {
            $this->error('Stripe secret key not configured. Please set STRIPE_SECRET in your .env file.');
            return 1;
        }

        $courseId = $this->option('course');
        $all = $this->option('all');
        $missing = $this->option('missing');

        if ($courseId) {
            // Create prices for a specific course
            $this->info("Creating Stripe prices for course ID: {$courseId}");
            $this->createPricesForCourse($courseId);
        } elseif ($all) {
            // Create prices for all courses
            $courses = Course::with(['modes', 'prices.billingPeriod'])->get();
            $this->info("Creating Stripe prices for all courses ({$courses->count()} courses)...");

            $bar = $this->output->createProgressBar($courses->count());
            $bar->start();

            foreach ($courses as $course) {
                $this->createPricesForCourse($course->id);
                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
            $this->info('Done!');
        } elseif ($missing) {
            // Create prices only for courses missing Stripe price IDs
            $this->info('Finding courses with missing Stripe price IDs...');

            $coursePrices = CoursePrice::whereNull('stripe_price_id')
                ->orWhere('stripe_price_id', '')
                ->orWhere(function ($query) {
                    $query->where('stripe_price_id', 'NOT LIKE', 'price_%');
                })
                ->with(['course.modes', 'billingPeriod'])
                ->get()
                ->groupBy('course_id');

            $this->info("Found {$coursePrices->count()} courses with missing Stripe price IDs");

            $bar = $this->output->createProgressBar($coursePrices->count());
            $bar->start();

            foreach ($coursePrices as $courseId => $prices) {
                $this->createPricesForCourse($courseId);
                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
            $this->info('Done!');
        } else {
            $this->error('Please specify --course=ID, --all, or --missing option');
            $this->info('');
            $this->info('Examples:');
            $this->info('  php artisan stripe:create-prices --course=1');
            $this->info('  php artisan stripe:create-prices --all');
            $this->info('  php artisan stripe:create-prices --missing');
            return 1;
        }

        return 0;
    }

    /**
     * Create Stripe prices for a specific course
     */
    private function createPricesForCourse($courseId)
    {
        try {
            // Run the job synchronously for immediate feedback
            $job = new CreateStripePrice($courseId);
            $job->handle();
            $this->line("  ✓ Created Stripe prices for course ID: {$courseId}");
        } catch (\Exception $e) {
            $this->error("  ✗ Failed for course ID {$courseId}: {$e->getMessage()}");
            Log::error("Failed to create Stripe prices for course {$courseId}: {$e->getMessage()}");
        }
    }
}
