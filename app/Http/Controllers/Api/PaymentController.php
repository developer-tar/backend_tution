<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MockExam;
use App\Models\Paper;
use App\Models\User;
use App\Services\BasketOrderFulfillmentService;
use App\Services\SubscriptionFulfillmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Cashier;
use Stripe\Stripe;
use Stripe\Price;
use Stripe\Product;

class PaymentController extends Controller
{
    public function checkout(Request $request)
    {
        try {
            $user = User::with(['cart' => function ($query) {
                $query->with(['mockExam', 'paper', 'course']);
            }])
                ->find($request->user()->id);

            $cartItems = $user->cart;

            if ($cartItems->isEmpty()) {
                return response()->json(['error' => 'Cart is empty'], 400);
            }

            $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
            $stripeSecret = config('constants.stripe_secret');

            if (!$stripeSecret) {
                Log::error("Stripe API key not configured");
                return response()->json([
                    'success' => false,
                    'message' => 'Error',
                    'data' => ['error' => 'Payment system is not configured. Please contact support.']
                ], 500);
            }

            Stripe::setApiKey($stripeSecret);

            $subscriptionItems = [];
            $oneTimeItems = [];
            $intervals = [];
            $intervalDetails = [];

            // Process cart items and get price_id from products if not set in cart
            foreach ($cartItems as $cartItem) {
                $priceId = $cartItem->price_id;

                $productType = $cartItem->product_type;
                $mockExamType = config('constants.product_types.mock');
                $mockExamTypeAlias = config('constants.product_types.mock_exam');
                $papersType = config('constants.product_types.papers');
                $paperType = config('constants.product_types.paper');

                Log::info("Processing cart item {$cartItem->id}", [
                    'product_id' => $cartItem->product_id,
                    'product_type' => $productType,
                    'product_type_class' => gettype($productType),
                    'price_id' => $priceId,
                    'has_mockExam' => $cartItem->relationLoaded('mockExam'),
                    'has_paper' => $cartItem->relationLoaded('paper'),
                    'has_course' => $cartItem->relationLoaded('course'),
                    'mock_type_value' => $mockExamType,
                    'papers_type_value' => $papersType,
                    'paper_type_value' => $paperType,
                    'is_mock_match' => ($productType == $mockExamType || $productType == $mockExamTypeAlias),
                    'is_paper_match' => ($productType == $papersType || $productType == $paperType),
                ]);

                // If price_id is null, try to get it from the product
                if (!$priceId) {
                    $product = null;

                    // Try to get product from relationship or load directly
                    if ($productType == $mockExamType || $productType == $mockExamTypeAlias) {
                        $product = $cartItem->mockExam ?? MockExam::find($cartItem->product_id);
                        // Validate stripe_price_id - must start with 'price_' to be valid
                        if ($product && $product->stripe_price_id && strpos($product->stripe_price_id, 'price_') === 0) {
                            $priceId = $product->stripe_price_id;
                            Log::info("Got price_id from mockExam", ['price_id' => $priceId, 'mock_exam_id' => $product->id]);
                        } elseif ($product && $product->price && $product->price > 0) {
                            // Mock exam has invalid or missing stripe_price_id, create a new one
                            if ($product->stripe_price_id) {
                                Log::warning("Mock exam has invalid stripe_price_id format, creating new one", [
                                    'mock_exam_id' => $product->id,
                                    'invalid_stripe_price_id' => $product->stripe_price_id
                                ]);
                            }
                            // Create Stripe price on the fly
                            try {
                                Stripe::setApiKey(config('constants.stripe_secret'));

                                if (!$product->stripe_product_id) {
                                    $stripeProduct = Product::create([
                                        'name' => $product->name,
                                        'description' => $product->description ?? '',
                                    ]);
                                    $product->update(['stripe_product_id' => $stripeProduct->id]);
                                }

                                $stripePrice = Price::create([
                                    'currency' => strtolower($product->currency ?? 'gbp'),
                                    'unit_amount' => intval($product->price * 100),
                                    'product' => $product->stripe_product_id,
                                ]);

                                $product->update(['stripe_price_id' => $stripePrice->id]);
                                $priceId = $stripePrice->id;

                                // Update cart item with the new price_id and refresh
                                $cartItem->update(['price_id' => $priceId]);
                                $cartItem->refresh(); // Reload from database

                                Log::info("Created Stripe price for mockExam and updated cart item", [
                                    'price_id' => $priceId,
                                    'mock_exam_id' => $product->id,
                                    'cart_item_id' => $cartItem->id,
                                    'cart_item_price_id_after_update' => $cartItem->price_id
                                ]);
                            } catch (\Stripe\Exception\ApiErrorException $e) {
                                Log::error("Stripe API error creating price for mock exam {$product->id}: {$e->getMessage()}", [
                                    'mock_exam_id' => $product->id,
                                    'mock_exam_price' => $product->price,
                                    'mock_exam_currency' => $product->currency,
                                    'stripe_error_type' => $e->getStripeCode(),
                                    'stripe_error_code' => $e->getCode(),
                                    'error' => $e->getMessage(),
                                    'cart_item_id' => $cartItem->id
                                ]);
                                continue; // Skip this item - cannot process without price_id
                            } catch (\Exception $e) {
                                Log::error("Failed to create Stripe price for mock exam {$product->id}: {$e->getMessage()}", [
                                    'mock_exam_id' => $product->id,
                                    'mock_exam_price' => $product->price,
                                    'mock_exam_currency' => $product->currency,
                                    'error' => $e->getMessage(),
                                    'trace' => $e->getTraceAsString(),
                                    'cart_item_id' => $cartItem->id
                                ]);
                                continue; // Skip this item - cannot process without price_id
                            }
                        } else {
                            Log::warning("Mock exam has no price or invalid price", [
                                'mock_exam_id' => $product ? $product->id : 'not found',
                                'mock_exam_price' => $product ? $product->price : 'N/A',
                                'cart_item_id' => $cartItem->id
                            ]);
                        }
                    } elseif ($productType == $papersType || $productType == $paperType) {
                        // Try to load paper from relationship first, then directly
                        if ($cartItem->relationLoaded('paper') && $cartItem->paper) {
                            $product = $cartItem->paper;
                        } else {
                            $product = Paper::find($cartItem->product_id);
                        }

                        Log::info("Processing paper product", [
                            'cart_item_id' => $cartItem->id,
                            'paper_id' => $cartItem->product_id,
                            'paper_loaded' => $product ? 'yes' : 'no',
                            'paper_stripe_price_id' => $product ? $product->stripe_price_id : null,
                            'paper_price' => $product ? $product->price : null,
                            'paper_currency' => $product ? $product->currency : null,
                            'paper_exists' => $product ? 'yes' : 'no',
                        ]);

                        if (!$product) {
                            Log::error("Paper not found for cart item", [
                                'cart_item_id' => $cartItem->id,
                                'paper_id' => $cartItem->product_id,
                            ]);
                            continue; // Skip this item
                        }

                        // Validate stripe_price_id - must start with 'price_' to be valid
                        if ($product->stripe_price_id && strpos($product->stripe_price_id, 'price_') === 0) {
                            $priceId = $product->stripe_price_id;
                            Log::info("Got price_id from paper", ['price_id' => $priceId, 'paper_id' => $product->id]);
                        } elseif ($product->price && $product->price > 0) {
                            // Paper has invalid or missing stripe_price_id, create a new one
                            if ($product->stripe_price_id) {
                                Log::warning("Paper has invalid stripe_price_id format, creating new one", [
                                    'paper_id' => $product->id,
                                    'invalid_stripe_price_id' => $product->stripe_price_id
                                ]);
                            }
                            // Create Stripe price on the fly
                            try {
                                Stripe::setApiKey(config('constants.stripe_secret'));

                                if (!$product->stripe_product_id) {
                                    $stripeProduct = Product::create([
                                        'name' => $product->name,
                                        'description' => $product->description ?? '',
                                    ]);
                                    $product->update(['stripe_product_id' => $stripeProduct->id]);
                                }

                                $stripePrice = Price::create([
                                    'currency' => strtolower($product->currency ?? 'gbp'),
                                    'unit_amount' => intval($product->price * 100),
                                    'product' => $product->stripe_product_id,
                                ]);

                                $product->update(['stripe_price_id' => $stripePrice->id]);
                                $priceId = $stripePrice->id;

                                // Update cart item with the new price_id and refresh
                                $cartItem->update(['price_id' => $priceId]);
                                $cartItem->refresh(); // Reload from database

                                Log::info("Created Stripe price for paper and updated cart item", [
                                    'price_id' => $priceId,
                                    'paper_id' => $product->id,
                                    'cart_item_id' => $cartItem->id,
                                    'cart_item_price_id_after_update' => $cartItem->price_id
                                ]);
                            } catch (\Stripe\Exception\ApiErrorException $e) {
                                Log::error("Stripe API error creating price for paper {$product->id}: {$e->getMessage()}", [
                                    'paper_id' => $product->id,
                                    'paper_price' => $product->price,
                                    'paper_currency' => $product->currency,
                                    'stripe_error_type' => $e->getStripeCode(),
                                    'stripe_error_code' => $e->getCode(),
                                    'error' => $e->getMessage(),
                                    'cart_item_id' => $cartItem->id
                                ]);
                                continue; // Skip this item - cannot process without price_id
                            } catch (\Exception $e) {
                                Log::error("Failed to create Stripe price for paper {$product->id}: {$e->getMessage()}", [
                                    'paper_id' => $product->id,
                                    'paper_price' => $product->price,
                                    'paper_currency' => $product->currency,
                                    'error' => $e->getMessage(),
                                    'trace' => $e->getTraceAsString(),
                                    'cart_item_id' => $cartItem->id
                                ]);
                                continue; // Skip this item - cannot process without price_id
                            }
                        } else {
                            Log::warning("Paper has no price or invalid price", [
                                'paper_id' => $product->id,
                                'paper_price' => $product->price,
                                'paper_currency' => $product->currency,
                                'cart_item_id' => $cartItem->id
                            ]);
                        }
                    } elseif ($cartItem->course) {
                        // For courses, price_id should be set from course_prices
                        Log::warning("Cart item {$cartItem->id} is a course but has no price_id");
                        continue; // Skip if no price_id for course
                    }
                }

                // Ensure we have the latest price_id from the cart item
                // If we just created a Stripe price, the cart item should have been updated
                if ($priceId) {
                    // If cart item doesn't have price_id but we have one, update it
                    if (!$cartItem->price_id || $cartItem->price_id != $priceId) {
                        $cartItem->update(['price_id' => $priceId]);
                        $cartItem->refresh();
                        Log::info("Updated cart item with price_id", [
                            'cart_item_id' => $cartItem->id,
                            'price_id' => $priceId,
                            'cart_item_price_id_after_update' => $cartItem->price_id
                        ]);
                    }
                } else {
                    // If we still don't have price_id, try to reload from cart item
                    $cartItem->refresh();
                    if ($cartItem->price_id) {
                        $priceId = $cartItem->price_id;
                        Log::info("Reloaded price_id from cart item", [
                            'cart_item_id' => $cartItem->id,
                            'price_id' => $priceId
                        ]);
                    }
                }

                if (!$priceId) {
                    Log::warning("Cart item {$cartItem->id} has no price_id and product has no stripe_price_id", [
                        'product_id' => $cartItem->product_id,
                        'product_type' => $cartItem->product_type,
                        'product_type_class' => gettype($cartItem->product_type),
                        'cart_item_price_id' => $cartItem->price_id,
                        'has_mockExam' => $cartItem->relationLoaded('mockExam'),
                        'has_paper' => $cartItem->relationLoaded('paper'),
                        'has_course' => $cartItem->relationLoaded('course'),
                        'mockExam_data' => $cartItem->mockExam ? [
                            'id' => $cartItem->mockExam->id,
                            'price' => $cartItem->mockExam->price,
                            'stripe_price_id' => $cartItem->mockExam->stripe_price_id,
                        ] : null,
                        'paper_data' => $cartItem->paper ? [
                            'id' => $cartItem->paper->id,
                            'price' => $cartItem->paper->price,
                            'stripe_price_id' => $cartItem->paper->stripe_price_id,
                        ] : null,
                    ]);
                    continue; // Skip items without price_id
                }

                $quantity = $cartItem->quantity ?? 1;

                try {
                    $price = Price::retrieve($priceId);
                    Log::info("Successfully retrieved Stripe price", [
                        'price_id' => $priceId,
                        'cart_item_id' => $cartItem->id,
                        'price_type' => isset($price->recurring) ? 'recurring' : 'one_time'
                    ]);

                    if (isset($price->recurring)) {
                        // Subscription item
                        $subscriptionItems[$priceId] = $quantity;

                        $interval = $price->recurring->interval;
                        $count = $price->recurring->interval_count;
                        $label = "every {$count} {$interval}" . ($count > 1 ? 's' : '');

                        $intervals[] = $interval . '_' . $count;
                        $intervalDetails[$priceId] = $label;
                    } else {
                        // One-time item
                        $oneTimeItems[$priceId] = $quantity;
                    }
                } catch (\Stripe\Exception\InvalidRequestException $e) {
                    Log::error("Failed to retrieve Stripe price {$priceId}: {$e->getMessage()}");
                    continue; // Skip invalid price IDs
                }
            }

            // Check if any items were processed
            if (empty($subscriptionItems) && empty($oneTimeItems)) {
                // Log detailed information about why no items were processed
                $cartItemDetails = $cartItems->map(function ($item) {
                    $product = null;
                    if ($item->mockExam) {
                        $product = $item->mockExam;
                    } elseif ($item->paper) {
                        $product = $item->paper;
                    } elseif ($item->course) {
                        $product = $item->course;
                    }

                    return [
                        'cart_item_id' => $item->id,
                        'product_id' => $item->product_id,
                        'product_type' => $item->product_type,
                        'price_id' => $item->price_id,
                        'product_price' => $product ? $product->price : null,
                        'product_stripe_price_id' => $product ? ($product->stripe_price_id ?? null) : null,
                    ];
                })->toArray();

                Log::error("No valid items found in cart for checkout", [
                    'user_id' => $user->id,
                    'cart_items' => $cartItemDetails,
                    'total_cart_items' => $cartItems->count()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Error',
                    'data' => [
                        'error' => 'No valid items found in cart. Please ensure all items have valid prices configured. If items were recently added, please try again or contact support.',
                        'debug' => 'Check server logs for detailed information about cart items'
                    ]
                ], 400);
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
                    'success_url' => $frontendUrl . '/payment-success?session_id={CHECKOUT_SESSION_ID}',
                    'cancel_url' => $frontendUrl . '/payment-cancel',
                    'payment_method_types' => ['card'],
                    'metadata' => [
                        'user_id' => $user->id,
                        'purchased_by' => config('constants.roles.PARENT'),
                    ],
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

                // Check if this is a mock exam purchase (single item)
                $isMockExamPurchase = count($oneTimeItems) === 1;
                $mockExamId = null;

                if ($isMockExamPurchase) {
                    // Try to find mock exam by price_id
                    $priceId = array_keys($oneTimeItems)[0];
                    $mockExam = MockExam::where('stripe_price_id', $priceId)->first();
                    if ($mockExam) {
                        $mockExamId = $mockExam->id;
                    }
                }

                $metadata = [
                    'user_id' => $user->id,
                    'purchased_by' => config('constants.roles.PARENT'),
                    'mock_exam_id' => $mockExamId, // null if not a mock exam purchase
                ];

                $checkout = $user->checkout($lineItems, [
                    'success_url' => $frontendUrl . '/payment-success?session_id={CHECKOUT_SESSION_ID}',
                    'cancel_url' => $frontendUrl . '/payment-cancel',
                    'payment_method_types' => ['card'],
                    'metadata' => $metadata,
                ]);
            }

            // Check if checkout was created
            if (!isset($checkout)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error',
                    'data' => ['error' => 'No valid items found in cart to checkout. Please ensure all items have valid prices.']
                ], 400);
            }

            return sendResponse(['url' => $checkout->url], 'Checkout session created');
        } catch (\Exception $e) {
            return errorLog("Failed to create checkout: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    /**
     * Verify payment status after checkout
     */
    public function verifyPayment(Request $request)
    {
        $request->validate([
            'session_id' => 'required|string',
        ]);

        try {
            $stripe = Cashier::stripe();
            $session = $stripe->checkout->sessions->retrieve($request->session_id);

            Log::info('Payment verification', [
                'session_id' => $session->id,
                'payment_status' => $session->payment_status,
                'mode' => $session->mode,
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'session_id' => $session->id,
                    'payment_status' => $session->payment_status,
                    'paid' => $session->payment_status === 'paid',
                    'mode' => $session->mode,
                ],
                'message' => $session->payment_status === 'paid'
                    ? 'Payment verified successfully'
                    : 'Payment not completed',
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to verify payment: {$e->getMessage()}", [
                'session_id' => $request->session_id,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to verify payment',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Fulfill basket order after payment success (create paper_purchases, mock_exam_purchases, clear cart).
     * Called when user lands on payment-success so records are created even if Stripe webhook did not fire (e.g. localhost).
     */
    public function fulfill(Request $request)
    {
        $request->validate([
            'session_id' => 'required|string',
        ]);

        try {
            $user = $request->user();
            $stripe = Cashier::stripe();
            $session = $stripe->checkout->sessions->retrieve($request->session_id, [
                'expand' => ['line_items.data.price'],
            ]);

            if ($session->payment_status !== 'paid') {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment not completed',
                ], 400);
            }

            $sessionArray = json_decode(json_encode($session), true);
            $sessionUserId = $sessionArray['metadata']['user_id'] ?? null;
            if ($sessionUserId && (int) $sessionUserId !== (int) $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 403);
            }

            $fulfilled = false;

            if ($session->mode === 'subscription') {
                // Save subscription to DB when user returns from Stripe (webhook may not fire on localhost)
                $subService = app(SubscriptionFulfillmentService::class);
                $fulfilled = $subService->fulfillFromSession($sessionArray);
                return response()->json([
                    'success' => true,
                    'message' => $fulfilled ? 'Subscription saved successfully' : 'Subscription already recorded',
                    'data' => ['fulfilled' => $fulfilled],
                ]);
            }

            if ($session->mode !== 'payment') {
                return response()->json([
                    'success' => true,
                    'message' => 'Not a basket one-time payment',
                    'data' => ['fulfilled' => false],
                ]);
            }

            $service = app(BasketOrderFulfillmentService::class);
            $fulfilled = $service->fulfill($sessionArray);

            return response()->json([
                'success' => true,
                'message' => $fulfilled ? 'Order fulfilled successfully' : 'Nothing to fulfill',
                'data' => ['fulfilled' => $fulfilled],
            ]);
        } catch (\Exception $e) {
            Log::error('Fulfill checkout failed: ' . $e->getMessage(), [
                'session_id' => $request->session_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fulfill order',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
