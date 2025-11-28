<?php

namespace App\Jobs;

use App\Models\MockExam;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;

class UpdateMockExamStripePrice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $mockExamId;
    public $timeout = 30;
    public $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct($mockExamId)
    {
        $this->mockExamId = $mockExamId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info("Starting Stripe price update for mock exam ID: {$this->mockExamId}");

            $mockExam = MockExam::with('format:id,name')->find($this->mockExamId);

            if (empty($mockExam)) {
                Log::error("Mock exam not found with ID: {$this->mockExamId}");
                return;
            }

            $stripe = new StripeClient(config('services.stripe.sk_test'));
            
            Log::info("Mock exam found", [
                'mock_exam_id' => $mockExam->id,
                'name' => $mockExam->name,
                'price' => $mockExam->price,
                'stripe_product_id' => $mockExam->stripe_product_id,
                'stripe_price_id' => $mockExam->stripe_price_id
            ]);

            // Prepare product update array
            $productUpdateArray = [
                'name' => $mockExam->name,
                'description' => $mockExam->description,
                'metadata' => [
                    'format' => $mockExam->format->name ?? 'N/A',
                    'format_id' => $mockExam->format_id ?? null
                ],
                'images' => [
                    $mockExam->getFirstMediaUrl('mock_exam_image') ?: config('constants.dummy_image'),
                ],
            ];

            $stripeProductId = $mockExam->stripe_product_id;

            // Update or create Stripe product
            if ($stripeProductId) {
                try {
                    // Update existing product
                    $stripe->products->update($stripeProductId, $productUpdateArray);
                    Log::info("Updated Stripe product {$stripeProductId} for mock exam {$mockExam->id}");
                } catch (\Exception $e) {
                    Log::warning("Failed to update Stripe product {$stripeProductId}, creating new one. Error: {$e->getMessage()}");
                    // Product doesn't exist, create new one
                    $stripeProduct = $stripe->products->create($productUpdateArray);
                    $stripeProductId = $stripeProduct->id;
                    $mockExam->update(['stripe_product_id' => $stripeProductId]);
                    Log::info("Created new Stripe product {$stripeProductId} for mock exam {$mockExam->id}");
                }
            } else {
                // Create new product if it doesn't exist
                $stripeProduct = $stripe->products->create($productUpdateArray);
                $stripeProductId = $stripeProduct->id;
                $mockExam->update(['stripe_product_id' => $stripeProductId]);
                Log::info("Created new Stripe product {$stripeProductId} for mock exam {$mockExam->id}");
            }

            // Handle Stripe price update
            $needsNewPrice = true;
            $existingPriceId = $mockExam->stripe_price_id;

            if ($existingPriceId) {
                try {
                    // Check if the existing price still matches
                    $existingStripePrice = $stripe->prices->retrieve($existingPriceId);
                    $existingAmount = $existingStripePrice->unit_amount / 100; // Convert from cents
                    
                    // Check if amount changed (with tolerance for floating point)
                    if (abs($existingAmount - (float)$mockExam->price) < 0.01) {
                        // Price hasn't changed, keep using existing price
                        $needsNewPrice = false;
                        Log::info("Price unchanged for mock exam {$mockExam->id}, keeping existing Stripe price {$existingPriceId}", [
                            'existing_amount' => $existingAmount,
                            'current_price' => $mockExam->price
                        ]);
                        
                        // Update product_id if it changed
                        if ($mockExam->stripe_product_id != $stripeProductId) {
                            $mockExam->update([
                                'stripe_product_id' => $stripeProductId,
                            ]);
                            Log::info("Updated stripe_product_id for mock exam {$mockExam->id}");
                        }
                    } else {
                        Log::info("Price changed for mock exam {$mockExam->id}, creating new Stripe price", [
                            'existing_amount' => $existingAmount,
                            'new_price' => $mockExam->price
                        ]);
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
                    'unit_amount' => intval($mockExam->price * 100),
                    'product' => $stripeProductId,
                ]);

                $mockExam->update([
                    'stripe_product_id' => $stripeProductId,
                    'stripe_price_id' => $stripePrice->id,
                ]);

                Log::info("Created new Stripe price {$stripePrice->id} for mock exam ID {$mockExam->id}", [
                    'mock_exam_id' => $mockExam->id,
                    'amount' => $mockExam->price,
                    'currency' => 'gbp'
                ]);
            }

            Log::info("Successfully completed Stripe price update for mock exam ID: {$this->mockExamId}");
        } catch (\Exception $e) {
            Log::error("Stripe price update failed for mock exam ID {$this->mockExamId}: {$e->getMessage()}, File: {$e->getFile()}, Line: {$e->getLine()}, Code: {$e->getCode()}");
            throw $e; // Re-throw to trigger job retry
        }
    }
}

