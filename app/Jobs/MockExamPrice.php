<?php

namespace App\Jobs;

use App\Models\Course;
use App\Models\MockExam;
use Error;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;

class MockExamPrice implements ShouldQueue
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
            
            $mockExam = MockExam::with('format:id,name')->find($this->mockExamId);

            if (empty($mockExam)) {
                Log::error("Mock exam not found with ID: {$this->mockExamId}");
                return;
            }

            $stripe = new StripeClient(config('services.stripe.sk_test'));// Initialize Stripe client

            $stripeArr = []; // initialize to avoid undefined array issue

            $productCreationArray = [
                'name' => $mockExam->name,
                'description' => $mockExam->description,
                'metadata' => [
                    'format' => $mockExam->format->name,
                    'format_id' => $mockExam->format->id
                ],
                'images' => [
                    $mockExam->getFirstMediaUrl('mock_exam_image') ?: config('constants.dummy_image'),
                ],
            ];// Initialize product creation array

            // Create product only if not already created
            if (!$mockExam->stripe_product_id) {
                $stripeProduct = $stripe->products->create($productCreationArray);
                $stripeArr['stripe_product_id'] = $stripeProduct->id;
            }

            // Create price only if not already created
            if (!$mockExam->stripe_price_id) {
                $stripePrice = $stripe->prices->create([
                    'currency' => 'gbp',
                    'unit_amount' => intval($mockExam->price * 100),
                    'product' => $mockExam->stripe_product_id ?? $stripeArr['stripe_product_id'],
                ]);
                $stripeArr['stripe_price_id'] = $stripePrice->id;
            }

            if (!empty($stripeArr)) {
                $mockExam->update($stripeArr);
            }// Update mock exam with Stripe IDs

        } catch (\Exception $e) {
            Log::error("Stripe price creation failed: {$e->getMessage()}, File: {$e->getFile()}, Line: {$e->getLine()}, Code: {$e->getCode()}.");
        }
    }

}
