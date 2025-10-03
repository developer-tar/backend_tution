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
            Stripe::setApiKey(config('constants.stripe_secret'));

            $subscriptionItems = [];
            $oneTimeItems = [];
            $intervals = [];
            $intervalDetails = [];

            // Cart ka analysis
            foreach ($userCart as $priceId => $qty) {
                $price = Price::retrieve($priceId);

                if (isset($price->recurring)) {
                    // Subscription item
                    $subscriptionItems[$priceId] = $qty;

                    $interval = $price->recurring->interval;
                    $count = $price->recurring->interval_count;
                    $label = "every {$count} {$interval}" . ($count > 1 ? 's' : '');

                    $intervals[] = $interval . '_' . $count;
                    $intervalDetails[$priceId] = $label;
                } else {
                    // One-time item
                    $oneTimeItems[$priceId] = $qty;
                }
            }

            // ❌ Mixed cart not allowed
            if (!empty($subscriptionItems) && !empty($oneTimeItems)) {
                return response()->json([
                    'error' => 'Cart cannot contain both subscription and one-time products. Please checkout separately.'
                ], 400);
            }

            // ✅ Subscription checkout
            if (!empty($subscriptionItems)) {
                if (count(array_unique($intervals)) > 1) {
                    return response()->json([
                        'error' => 'All subscription items must have the same billing interval.',
                        'details' => $intervalDetails,
                        'hint' => 'Please remove plans with a different interval to continue.'
                    ], 400);
                }

                $priceIds = array_keys($subscriptionItems);
                $subscriptionBuilder = $user->newSubscription('default', $priceIds);

                foreach ($subscriptionItems as $priceId => $quantity) {
                    $subscriptionBuilder->quantity($quantity, $priceId);
                }

                $checkout = $subscriptionBuilder->checkout([
                    'success_url' => $frontendUrl . '/payment-success',
                    'cancel_url' => $frontendUrl . '/payment-cancel',
                ]); 
            }

            // ✅ One-time checkout with Cashier
            if (!empty($oneTimeItems)) {
                $lineItems = [];
                foreach ($oneTimeItems as $priceId => $quantity) {
                    $lineItems[] = [
                        'price' => $priceId,
                        'quantity' => $quantity,
                    ];
                }

                $checkout = $user->checkout($lineItems, [
                    'success_url' => $frontendUrl . '/payment-success',
                    'cancel_url' => $frontendUrl . '/payment-cancel',
                    'metadata' => [
                        'type' => 'cart_checkout',
                        'user_id' => $user->id,
                        'purchased_by' => config('constants.roles.PARENT'), // Assuming parent role for cart
                        'items_count' => count($oneTimeItems),
                    ],
                ]);
               
            }
             return sendResponse('Checkout session created', ['url' => $checkout->url]);

        } catch (\Exception $e) {
            return errorLog("Failed to create checkout: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
           
        }
    }
}
