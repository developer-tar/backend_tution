<?php

namespace App\Jobs;

use App\Models\Paper;
use Error;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;

class PaperPrice implements ShouldQueue
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
            
            $paper = Paper::with('format:id,name')->find($this->paperId);

            if (empty($paper)) {
                Log::error("Paper not found with ID: {$this->paperId}");
                return;
            }

            $stripe = new StripeClient(config('services.stripe.sk_test'));// Initialize Stripe client

            $stripeArr = []; // initialize to avoid undefined array issue

            $productCreationArray = [
                'name' => $paper->name,
                'description' => $paper->description,
                'metadata' => [
                    'format' => $paper->format->name,
                    'format_id' => $paper->format->id
                ],
                'images' => [
                    $paper->getFirstMediaUrl('paper_image') ?: config('constants.dummy_image'),
                ],
            ];// Initialize product creation array

            // Create product only if not already created
            if (!$paper->stripe_product_id) {
                $stripeProduct = $stripe->products->create($productCreationArray);
                $stripeArr['stripe_product_id'] = $stripeProduct->id;
            }

            // Create price only if not already created
            if (!$paper->stripe_price_id) {
                $stripePrice = $stripe->prices->create([
                    'currency' => 'gbp',
                    'unit_amount' => intval($paper->price * 100),
                    'product' => $paper->stripe_product_id ?? $stripeArr['stripe_product_id'],
                ]);
                $stripeArr['stripe_price_id'] = $stripePrice->id;
            }

            if (!empty($stripeArr)) {
                $paper->update($stripeArr);
            }// Update paper with Stripe IDs

        } catch (\Exception $e) {
            Log::error("Stripe price creation failed: {$e->getMessage()}, File: {$e->getFile()}, Line: {$e->getLine()}, Code: {$e->getCode()}.");
        }
    }

}







