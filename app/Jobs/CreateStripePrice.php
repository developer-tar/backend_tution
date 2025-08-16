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

class CreateStripePrice implements ShouldQueue
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
        $this->onQueue('stripe');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $course = Course::with(['modes', 'prices.billingPeriod'])->findOrFail($this->courseId);

            if ($course->modes->isEmpty()) {
                return;
            }

            $stripe = new StripeClient(config('services.stripe.sk_test'));

            foreach ($course->modes as $mode) {
                $productCreationArray = [
                    'name' => $course->name,
                    'description' => $course->description,
                    'metadata' => [
                        'mode' => $mode->name,
                    ],
                    'images' => [
                        $course->getFirstMediaUrl('course_image') ?: config('constants.dummy_image'),
                    ],
                ];

                $stripeProduct = $stripe->products->create($productCreationArray);
                $stripeProductId = $stripeProduct->id;

                // Filter prices by mode
                $modePrices = $course->prices->where('mode_id', $mode->id);

                foreach ($modePrices as $coursePrice) {
                    if (!$coursePrice->billingPeriod) {
                        Log::warning("Billing period missing for price ID {$coursePrice->id}");
                        continue;
                    }

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
                }
            }
        } catch (\Exception $e) {
            Log::error("Stripe price creation failed: {$e->getMessage()}, File: {$e->getFile()}, Line: {$e->getLine()}, Code: {$e->getCode()}.");
        }
    }
}
