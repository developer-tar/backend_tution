<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PaymentController extends Controller {
    public function checkout(Request $request) {

        $stripePriceId = $request->input('stripe_price_id');
        $quantity = $request->input('quantity', 1);
        $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
        $checkout = $request->user()->checkout([
            $stripePriceId => $quantity
        ], [
            'success_url' => $frontendUrl . '/payment-success',
            'cancel_url' => $frontendUrl . '/payment-cancel',
        ]);

        return response()->json([
            'url' => $checkout->url,
        ]);
    }
}
