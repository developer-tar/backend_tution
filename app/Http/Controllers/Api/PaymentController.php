<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function checkout(Request $request)
    {
        $user = User::with('cart:id,user_id,price_id,quantity')
            ->find($request->user()->id);

        $userCart = $user->cart->pluck('quantity', 'price_id')->toArray();

        if($userCart == null) {
            return response()->json(['error' => 'Cart is empty'], 400);
        }
        // $stripePriceId = $request->input('stripe_price_id');
        // $quantity = $request->input('quantity', 1);
        $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
        $checkout = $request->user()->checkout($userCart, [
            'success_url' => $frontendUrl . '/payment-success',
            'cancel_url' => $frontendUrl . '/payment-cancel',
        ]);

        return response()->json([
            'url' => $checkout->url,
        ]);
    }
}
