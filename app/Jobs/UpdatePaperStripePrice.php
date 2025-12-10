<?php

namespace App\Jobs;

use App\Models\Paper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;

class UpdatePaperStripePrice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $paperId;
    public $timeout = 30;
    public $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct($paperId)
    {
        $this->paperId = $paperId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info("Starting Stripe price update for paper ID: {$this->paperId}");

            $paper = Paper::with('format:id,name')->find($this->paperId);

            if (empty($paper)) {
                Log::error("Paper not found with ID: {$this->paperId}");
                return;
            }

            $stripe = new StripeClient(config('services.stripe.sk_test'));
            
            Log::info("Paper found", [
                'paper_id' => $paper->id,
                'name' => $paper->name,
                'price' => $paper->price,
                'stripe_product_id' => $paper->stripe_product_id,
                'stripe_price_id' => $paper->stripe_price_id
            ]);

            // Prepare product update array
            $productUpdateArray = [
                'name' => $paper->name,
                'description' => $paper->description,
                'metadata' => [
                    'format' => $paper->format->name ?? 'N/A',
                    'format_id' => $paper->format_id ?? null
                ],
                'images' => [
                    $paper->getFirstMediaUrl('paper_image') ?: config('constants.dummy_image'),
                ],
            ];

            $stripeProductId = $paper->stripe_product_id;

            // Update or create Stripe product
            if ($stripeProductId) {
                try {
                    // Update existing product
                    $stripe->products->update($stripeProductId, $productUpdateArray);
                    Log::info("Updated Stripe product {$stripeProductId} for paper {$paper->id}");
                } catch (\Exception $e) {
                    Log::warning("Failed to update Stripe product {$stripeProductId}, creating new one. Error: {$e->getMessage()}");
                    // Product doesn't exist, create new one
                    $stripeProduct = $stripe->products->create($productUpdateArray);
                    $stripeProductId = $stripeProduct->id;
                    $paper->update(['stripe_product_id' => $stripeProductId]);
                    Log::info("Created new Stripe product {$stripeProductId} for paper {$paper->id}");
                }
            } else {
                // Create new product if it doesn't exist
                $stripeProduct = $stripe->products->create($productUpdateArray);
                $stripeProductId = $stripeProduct->id;
                $paper->update(['stripe_product_id' => $stripeProductId]);
                Log::info("Created new Stripe product {$stripeProductId} for paper {$paper->id}");
            }

            // Handle Stripe price update
            $needsNewPrice = true;
            $existingPriceId = $paper->stripe_price_id;

            if ($existingPriceId) {
                try {
                    // Check if the existing price still matches
                    $existingStripePrice = $stripe->prices->retrieve($existingPriceId);
                    $existingAmount = $existingStripePrice->unit_amount / 100; // Convert from cents
                    
                    // Check if amount changed (with tolerance for floating point)
                    if (abs($existingAmount - (float)$paper->price) < 0.01) {
                        // Price hasn't changed, keep using existing price
                        $needsNewPrice = false;
                        Log::info("Price unchanged for paper {$paper->id}, keeping existing Stripe price {$existingPriceId}", [
                            'existing_amount' => $existingAmount,
                            'current_price' => $paper->price
                        ]);
                        
                        // Update product_id if it changed
                        if ($paper->stripe_product_id != $stripeProductId) {
                            $paper->update([
                                'stripe_product_id' => $stripeProductId,
                            ]);
                            Log::info("Updated stripe_product_id for paper {$paper->id}");
                        }
                    } else {
                        Log::info("Price changed for paper {$paper->id}, creating new Stripe price", [
                            'existing_amount' => $existingAmount,
                            'new_price' => $paper->price
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
                    'unit_amount' => intval($paper->price * 100),
                    'product' => $stripeProductId,
                ]);

                $paper->update([
                    'stripe_product_id' => $stripeProductId,
                    'stripe_price_id' => $stripePrice->id,
                ]);

                Log::info("Created new Stripe price {$stripePrice->id} for paper ID {$paper->id}", [
                    'paper_id' => $paper->id,
                    'amount' => $paper->price,
                    'currency' => 'gbp'
                ]);
            }

            Log::info("Successfully completed Stripe price update for paper ID: {$this->paperId}");
        } catch (\Exception $e) {
            Log::error("Stripe price update failed for paper ID {$this->paperId}: {$e->getMessage()}, File: {$e->getFile()}, Line: {$e->getLine()}, Code: {$e->getCode()}");
            throw $e; // Re-throw to trigger job retry
        }
    }
}







