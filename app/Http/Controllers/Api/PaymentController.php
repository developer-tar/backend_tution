<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;
use Stripe\Price;
class PaymentController extends Controller
{
    public function checkout(Request $request)
    {
        try {
            $user = User::with('cart:id,user_id,price_id,quantity')
                ->find($request->user()->id);

            $userCart = $user->cart->pluck('quantity', 'price_id')->toArray();

            if (empty($userCart)) {
                return response()->json(['error' => 'Cart is empty'], 400);
            }

            $frontendUrl = config('app.frontend_url', 'http://localhost:3000');

            // --- Step 1: Check intervals ---
            Stripe::setApiKey(config('constants.stripe_secret')); // Important for raw SDK calls

            $intervals = [];
            $intervalDetails = [];

            foreach ($userCart as $priceId => $qty) {
                $price = Price::retrieve($priceId);

                if (!isset($price->recurring)) {
                    return response()->json([
                        'error' => "Price {$priceId} is not a subscription price"
                    ], 400);
                }

                $interval = $price->recurring->interval;        // e.g. month, year
                $count = $price->recurring->interval_count; // e.g. 1, 12
                $label = "every {$count} {$interval}" . ($count > 1 ? 's' : '');

                $intervals[] = $interval . '_' . $count; // Unique identifier
                $intervalDetails[$priceId] = $label;
            }

            // --- Step 2: Block if mixed intervals ---
            if (count(array_unique($intervals)) > 1) {
                return response()->json([
                    'error' => 'All subscription items must have the same billing interval.',
                    'details' => $intervalDetails,
                    'hint' => 'Please remove plans with a different interval to continue.'
                ], 400);
            }

            // --- Step 3: Proceed with subscription checkout ---
            $priceIds = array_keys($userCart);
            $subscriptionBuilder = $user->newSubscription('default', $priceIds);

            foreach ($userCart as $priceId => $quantity) {
                $subscriptionBuilder->quantity($quantity, $priceId);
            }

            $checkout = $subscriptionBuilder->checkout([
                'success_url' => $frontendUrl . '/payment-success',
                'cancel_url' => $frontendUrl . '/payment-cancel',
            ]);

            return response()->json([
                'url' => $checkout->url,
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to create subscription. Message => {$e->getMessage()}, File => {$e->getFile()}, Line => {$e->getLine()}, Code => {$e->getCode()}.");
            return response()->json(['error' => 'An error occurred while processing your request.'], 500);
        }
    }
}
