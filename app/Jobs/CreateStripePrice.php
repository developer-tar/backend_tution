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
            $course = Course::with('modes', 'prices')->findOrFail($this->courseId);

            $stripe = new StripeClient(config('services.stripe.sk_test'));
            if ($course->modes->isEmpty()) {
                return;
            }
            foreach ($course->modes as $mode) {
                $productCreationArray = [
                    'name' => $course->name,
                    'description' => $course->description,
                    "metadata" => [
                        'course_id' => $course->id,
                        'mode_id' => $mode->id ?? null,
                        'mode_name' => $mode->name ?? null,
                    ],
                    // 'images' => [$course->getFirstMediaUrl('course_image')],
                    "images" => ["https://images.pexels.com/photos/674010/pexels-photo-674010.jpeg?_gl=1*1mnuiw2*_ga*ODQxNTgxMDUwLjE3NTQwMzAyMjA.*_ga_8JE65Q40S6*czE3NTQwMzAyMjAkbzEkZzAkdDE3NTQwMzAyMjAkajYwJGwwJGgw"],
                ];

                $stripeProduct = $stripe->products->create($productCreationArray);
                $stripeProductId = $stripeProduct->id;

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

                    $coursePrice->where('mode_id', $mode->id)->update([
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
                        'course_id' => $course->id,
                        'mode_id' => $mode->id,
                        'mode_name' => $mode->name,
                    ],
                    'images' => [
                        $course->getFirstMediaUrl('course_image') ?: 'https://images.pexels.com/photos/674010/pexels-photo-674010.jpeg'
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
