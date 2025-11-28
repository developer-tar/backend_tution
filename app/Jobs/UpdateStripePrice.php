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

class UpdateStripePrice implements ShouldQueue
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
            $course = Course::with(['modes', 'prices.billingPeriod'])->findOrFail($this->courseId);

            if ($course->modes->isEmpty()) {
                Log::info("Skipping Stripe update for course ID: {$this->courseId} - no modes assigned");
                return;
            }

            $stripe = new StripeClient(config('services.stripe.sk_test'));
            Log::info("Starting Stripe price update for course ID: {$this->courseId}", [
                'course_name' => $course->name,
                'modes_count' => $course->modes->count(),
                'prices_count' => $course->prices->count()
            ]);

            foreach ($course->modes as $mode) {
                // Get existing prices for this mode to check for existing product
                $modePrices = $course->prices->where('mode_id', $mode->id);
                
                if ($modePrices->isEmpty()) {
                    continue;
                }

                // Check if we have an existing Stripe product for this mode
                $existingProductId = $modePrices->first()->stripe_product_id;

                $productUpdateArray = [
                    'name' => $course->name,
                    'description' => $course->description,
                    'metadata' => [
                        'mode' => $mode->name,
                    ],
                    'images' => [
                        $course->getFirstMediaUrl('course_image') ?: config('constants.dummy_image'),
                    ],
                ];

                $stripeProductId = null;

                if ($existingProductId) {
                    try {
                        // Update existing product
                        $stripe->products->update($existingProductId, $productUpdateArray);
                        $stripeProductId = $existingProductId;
                        Log::info("Updated Stripe product {$existingProductId} for course {$course->id}, mode {$mode->name}");
                    } catch (\Exception $e) {
                        // If product doesn't exist in Stripe, create a new one
                        Log::warning("Stripe product {$existingProductId} not found, creating new product. Error: {$e->getMessage()}");
                        $stripeProduct = $stripe->products->create($productUpdateArray);
                        $stripeProductId = $stripeProduct->id;
                    }
                } else {
                    // Create new product if it doesn't exist
                    $stripeProduct = $stripe->products->create($productUpdateArray);
                    $stripeProductId = $stripeProduct->id;
                    Log::info("Created new Stripe product {$stripeProductId} for course {$course->id}, mode {$mode->name}");
                }

                // Process prices - Stripe prices are immutable, so we create new ones
                foreach ($modePrices as $coursePrice) {
                    if (!$coursePrice->billingPeriod) {
                        Log::warning("Billing period missing for price ID {$coursePrice->id}");
                        continue;
                    }

                    // Check if the price amount has changed or if price doesn't exist
                    $needsNewPrice = true;
                    $existingPriceId = $coursePrice->stripe_price_id;

                    if ($existingPriceId) {
                        try {
                            // Check if the existing price still matches
                            $existingStripePrice = $stripe->prices->retrieve($existingPriceId);
                            $existingAmount = $existingStripePrice->unit_amount / 100; // Convert from cents
                            
                            // Check if amount or billing period changed
                            if (abs($existingAmount - (float)$coursePrice->amount) < 0.01 && 
                                $existingStripePrice->recurring['interval_count'] == $coursePrice->billingPeriod->period) {
                                // Price hasn't changed, keep using existing price
                                $needsNewPrice = false;
                                
                                // Update product_id if it changed
                                if ($coursePrice->stripe_product_id != $stripeProductId) {
                                    $coursePrice->update([
                                        'stripe_product_id' => $stripeProductId,
                                    ]);
                                }
                            }
                        } catch (\Exception $e) {
                            // Price doesn't exist in Stripe, need to create new one
                            Log::warning("Stripe price {$existingPriceId} not found, creating new price. Error: {$e->getMessage()}");
                        }
                    }

                    if ($needsNewPrice) {
                        // Create new Stripe price (prices are immutable in Stripe)
                        $stripePrice = $stripe->prices->create([
                            'currency' => 'gbp',
                            'unit_amount' => intval($coursePrice->amount * 100),
                            'recurring' => [
                                'interval' => 'month',
                                'interval_count' => $coursePrice->billingPeriod->period,
                            ],
                            'product' => $stripeProductId,
                        ]);

                        $coursePrice->update([
                            'stripe_product_id' => $stripeProductId,
                            'stripe_price_id' => $stripePrice->id,
                        ]);

                        Log::info("Created new Stripe price {$stripePrice->id} for course price ID {$coursePrice->id}", [
                            'course_id' => $course->id,
                            'amount' => $coursePrice->amount,
                            'billing_period' => $coursePrice->billingPeriod->name ?? 'N/A'
                        ]);
                    }
                }
            }
            
            Log::info("Successfully completed Stripe price update for course ID: {$this->courseId}");
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error("Course not found for Stripe update. Course ID: {$this->courseId}");
            throw $e;
        } catch (\Exception $e) {
            Log::error("Stripe price update failed for course ID: {$this->courseId}. Message => {$e->getMessage()}, File => {$e->getFile()}, Line => {$e->getLine()}, Code => {$e->getCode()}.");
            // Re-throw to trigger job retry mechanism
            throw $e;
        }
    }
}

