<?php

namespace App\Jobs;

use App\Models\Course;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

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
    public function __construct($courseId, $amount)
    {
        $this->courseId = $courseId;
        $this->amount = $amount;
        $this->onQueue('stripe'); 
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $course = Course::findOrFail($this->courseId);

            $stripe = new \Stripe\StripeClient(config('services.stripe.sk_test'));

            $price = $stripe->prices->create([
                'currency' => 'usd',
                'unit_amount' => $this->amount * 100,
                'product' => $course->product_id,
            ]);

            $course->update(['price_id' => $price->id]);
        } catch (\Exception $e) {
            Log::error("Stripe price creation failed: {$e->getMessage()}");
        }
    }
}
