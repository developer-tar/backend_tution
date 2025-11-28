<?php

namespace App\Jobs;

use App\Models\Course;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;

class DeleteStripeProduct implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $courseId;
    public $timeout = 30;
    public $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct($courseId)
    {
        $this->courseId = $courseId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $course = Course::with('prices')->find($this->courseId);

            if (!$course) {
                Log::warning("Course not found for Stripe product deletion. Course ID: {$this->courseId}");
                return;
            }

            $stripe = new StripeClient(config('services.stripe.sk_test'));

            // Get all unique Stripe product IDs from course prices
            $stripeProductIds = $course->prices()
                ->whereNotNull('stripe_product_id')
                ->distinct()
                ->pluck('stripe_product_id')
                ->unique()
                ->filter()
                ->toArray();

            if (empty($stripeProductIds)) {
                Log::info("No Stripe products found for course {$this->courseId}");
                return;
            }

            // Delete each Stripe product
            foreach ($stripeProductIds as $productId) {
                try {
                    $stripe->products->delete($productId);
                    Log::info("Deleted Stripe product {$productId} for course {$this->courseId}");
                } catch (\Stripe\Exception\InvalidRequestException $e) {
                    // Product might already be deleted or not exist
                    if ($e->getStripeCode() === 'resource_missing') {
                        Log::warning("Stripe product {$productId} not found (may already be deleted)");
                    } else {
                        Log::error("Failed to delete Stripe product {$productId}: {$e->getMessage()}");
                    }
                } catch (\Exception $e) {
                    Log::error("Error deleting Stripe product {$productId}: {$e->getMessage()}, File: {$e->getFile()}, Line: {$e->getLine()}");
                }
            }
        } catch (\Exception $e) {
            Log::error("Stripe product deletion failed: {$e->getMessage()}, File: {$e->getFile()}, Line: {$e->getLine()}, Code: {$e->getCode()}.");
        }
    }
}

