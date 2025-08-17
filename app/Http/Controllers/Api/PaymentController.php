<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function subscriptionCheckout(Request $request)
    {
        try {
            
            $user = User::with('cart:id,user_id,price_id,quantity')
                ->find($request->user()->id);
             dd($user->paymentMethods());

            $userCart = $user->cart->pluck('quantity', 'price_id')->toArray();

            if (empty($userCart)) {
                return response()->json(['error' => 'Cart is empty'], 400);
            }

            $frontendUrl = config('app.frontend_url', 'http://localhost:3000');

            
            // Get all price IDs from cart
            $priceIds = array_keys($userCart);
           
            // Create subscription
            $subscriptionBuilder = $user->newSubscription('default', ['price_1RwiWrPBXobf6dxwCzUGPqr9', 'price_1RwhxzPBXobf6dxwK9lucrLU']);
            // $subscriptionBuilder = $subscriptionBuilder->quantity([1=> 'price_1RwiWrPBXobf6dxwCzUGPqr9', '1'=> 'price_1RwiWqPBXobf6dxwcpQ428BM']);
            // Set quantity per price
            // foreach ($userCart as $priceId => $quantity) {
            //     $subscriptionBuilder->quantity($quantity, $priceId);
            // }
            //     dd($subscriptionBuilder->toArray());
            // Checkout session
                $checkout = $subscriptionBuilder->checkout([
                    'success_url' => $frontendUrl . '/payment-success',
                    'cancel_url' => $frontendUrl . '/payment-cancel',
                    'mode' => 'subscription',
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
