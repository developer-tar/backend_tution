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

class CreateStripePrice implements ShouldQueue {
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $courseId;
    public $amount;
    public $timeout = 30;
    public $tries = 3;

    /**
     * The queue connection name.
     *
     * @var string|null
     */

    /**
     * Create a new job instance.
     */
    public function __construct($courseId) {
        $this->courseId = $courseId;
        $this->onQueue('stripe');
    }

    /**
     * Execute the job.
     */
    public function handle(): void {
        try {
            $course = Course::findOrFail($this->courseId);

            $stripe = new StripeClient(config('services.stripe.sk_test'));
            $productCreationArray = [
                'name' => $course->name,
                'description' => $course->description,
                "metadata" => [
                    'course_id' => $course->id,
                ],
                // 'images' => [$course->getFirstMediaUrl('course_image')],
                "images" => ["https://images.pexels.com/photos/674010/pexels-photo-674010.jpeg?_gl=1*1mnuiw2*_ga*ODQxNTgxMDUwLjE3NTQwMzAyMjA.*_ga_8JE65Q40S6*czE3NTQwMzAyMjAkbzEkZzAkdDE3NTQwMzAyMjAkajYwJGwwJGgw"],
            ];

            $stripeProduct = $stripe->products->create($productCreationArray);
            $stripeProductId = $stripeProduct->id;

            $course->update(['product_id' => $stripeProductId]);

            foreach ($course->prices as $coursePrice) {
                if (!$coursePrice->billingPeriod) {
                    Log::warning("Billing period missing for price ID {$coursePrice->id}");

                    continue;
                }
                $stripePrice = $stripe->prices->create([
                    // 'currency' => config('constants.currency')['£'],
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
            }
        } catch (\Exception $e) {
            Log::error("Stripe price creation failed: {$e->getMessage()}, File: {$e->getFile()}, Line: {$e->getLine()}, Code: {$e->getCode()}.");
        }
    }
}
