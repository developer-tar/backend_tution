<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use Illuminate\Http\Request;
use App\Http\Requests\Api\Cart\{AddToCartRequest, UpdateCartRequest, RemoveFromCartRequest};
use Exception;

use Illuminate\Support\Facades\{Log, Response};

class CartController extends Controller
{

    public function index(Request $request)
    {
        try {
            // Check if Bearer token is provided
            if ($request->bearerToken()) {
                Log::info('Bearer token detected in cart request', [
                    'token_length' => strlen($request->bearerToken()),
                    'token_start' => substr($request->bearerToken(), 0, 20) . '...'
                ]);

                // Try to authenticate with Bearer token
                try {
                    $user = auth('api')->user();
                    if (!$user) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Invalid or expired Bearer token',
                            'error' => 'Token authentication failed - please login again to get a fresh token'
                        ], 401);
                    }
                    $userId = $user->id;
                    Log::info('Bearer token authentication successful', ['user_id' => $userId]);
                } catch (Exception $e) {
                    Log::error('Bearer token validation error', ['error' => $e->getMessage()]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Bearer token validation failed',
                        'error' => 'Token signature verification failed - please login again',
                        'debug' => $e->getMessage()
                    ], 401);
                }
            } else {
                // No Bearer token, use session authentication
                $userId = auth()->id();
                Log::info('No Bearer token, using session auth', ['user_id' => $userId]);
            }

            $sessionId = session()->getId();

            Log::info('Fetching cart items', [
                'user_id' => $userId,
                'session_id' => $sessionId,
                'has_bearer_token' => $request->bearerToken() ? 'yes' : 'no'
            ]);

            // Query cart items - if user is logged in, get by user_id, otherwise by session_id
            // Also include items with matching session_id if user_id is set (for migrated guest carts)
            $cartItems = Cart::with(['price'])
                ->where(function ($q) use ($userId, $sessionId) {
                    if ($userId) {
                        // User is logged in - get items by user_id OR session_id (for guest cart migration)
                        $q->where('user_id', $userId)
                            ->orWhere('session_id', $sessionId);
                    } else {
                        // User is not logged in - get items by session_id only
                        $q->where('session_id', $sessionId);
                    }
                })
                ->get();

            Log::info('Cart items found', [
                'count' => $cartItems->count(),
                'user_id' => $userId,
                'session_id' => $sessionId,
                'items' => $cartItems->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'product_id' => $item->product_id,
                        'product_type' => $item->product_type,
                        'user_id' => $item->user_id,
                        'session_id' => $item->session_id,
                    ];
                })->toArray()
            ]);

            // Load relationships conditionally based on product_type
            $cartItems->load([
                'course',
                'mockExam',
                'paper.format'
            ]);

            if ($cartItems->isNotEmpty()) {
                $cartItems = $cartItems->transform(function ($item) {
                    $productType = $item->product_type;
                    $quantity = $item->quantity ?? 1;

                    // Convert product_type integer back to string key for consistency with frontend API
                    $productTypeMap = array_flip(config('constants.product_types'));
                    $productTypeKey = $productTypeMap[$productType] ?? null;
                    // Prefer 'papers' over 'paper' if both exist
                    if ($productType == config('constants.product_types.papers')) {
                        $productTypeKey = 'papers';
                    }

                    // Initialize response array with common structure
                    $response = [
                        'cart_id' => $item->id,
                        'product_type' => $productTypeKey ?? $productType, // Use string key if available, fallback to integer
                        'quantity' => $quantity,
                        'price_id' => $item->price_id ?? null,
                    ];

                    // Check if it's a course
                    if ($productType == config('constants.product_types.course') && $item->course) {
                        $course = $item->course;
                        $amount = $item->price?->amount ?? 0;

                        if ($course->getFirstMediaUrl('course_image') == "") {
                            $image = config('constants.dummy_image');
                        } else {
                            $image = $course->getFirstMediaUrl('course_image');
                        }

                        $response['course_name'] = $course->name ?? 'Unknown Product';
                        $response['course_image'] = $image;
                        $response['course_price'] = $amount;
                        $response['total_price'] = $quantity * $amount;
                    }
                    // Check if it's a mock exam
                    elseif (($productType == config('constants.product_types.mock') || $productType == config('constants.product_types.mock_exam')) && $item->mockExam) {
                        $mockExam = $item->mockExam;
                        $amount = $mockExam->price ?? 0;

                        if ($mockExam->getFirstMediaUrl('mock_exam_image') == "") {
                            $image = config('constants.dummy_image');
                        } else {
                            $image = $mockExam->getFirstMediaUrl('mock_exam_image');
                        }

                        // Course keys maintained for consistency
                        $response['course_name'] = $mockExam->name ?? 'Unknown Product';
                        $response['course_image'] = $image;
                        $response['course_price'] = $amount;
                        $response['total_price'] = $quantity * $amount;

                        // Additional mock exam specific keys
                        $response['mock_exam_name'] = $mockExam->name ?? null;
                        $response['mock_exam_description'] = $mockExam->description ?? null;
                        $response['mock_exam_format'] = $mockExam->format ?? null;
                        $response['mock_exam_duration_minutes'] = $mockExam->duration_minutes ?? null;
                        $response['mock_exam_total_marks'] = $mockExam->total_marks ?? null;
                        $response['mock_exam_currency'] = $mockExam->currency ?? null;
                        $response['mock_exam_slug'] = $mockExam->slug ?? null;
                    }
                    // Check if it's a paper
                    elseif (($productType == config('constants.product_types.papers') || $productType == config('constants.product_types.paper'))) {
                        if (!$item->paper) {
                            Log::warning('Cart item has paper product_type but paper relationship is null', [
                                'cart_item_id' => $item->id,
                                'product_id' => $item->product_id,
                                'product_type' => $productType
                            ]);
                            // Return minimal response even if paper is not found
                            $response['course_name'] = 'Paper (ID: ' . $item->product_id . ')';
                            $response['course_image'] = config('constants.dummy_image');
                            $response['course_price'] = 0;
                            $response['total_price'] = 0;
                            return $response;
                        }

                        $paper = $item->paper;
                        $amount = $paper->price ?? 0;

                        if ($paper->getFirstMediaUrl('paper_image') == "") {
                            $image = config('constants.dummy_image');
                        } else {
                            $image = $paper->getFirstMediaUrl('paper_image');
                        }

                        // Course keys maintained for consistency
                        $response['course_name'] = $paper->name ?? 'Unknown Product';
                        $response['course_image'] = $image;
                        $response['course_price'] = $amount;
                        $response['total_price'] = $quantity * $amount;

                        // Additional paper specific keys
                        $response['paper_name'] = $paper->name ?? null;
                        $response['paper_description'] = $paper->description ?? null;
                        $response['paper_format'] = $paper->format?->name ?? null;
                        $response['paper_duration_minutes'] = $paper->duration_minutes ?? null;
                        $response['paper_total_marks'] = $paper->total_marks ?? null;
                        $response['paper_currency'] = $paper->currency ?? null;
                        $response['paper_slug'] = $paper->slug ?? null;
                        $response['paper_stripe_product_id'] = $paper->stripe_product_id ?? null;
                        $response['paper_stripe_price_id'] = $paper->stripe_price_id ?? null;
                    }

                    return $response;
                });

                $response = [
                    'success' => true,
                    'data' => $cartItems,
                    'message' => 'Cart details fetch Successfully!!',
                ];
                return Response::json($response, 200);
            } else {
                // Return empty array instead of error when cart is empty
                Log::info('Cart is empty', [
                    'user_id' => $userId,
                    'session_id' => $sessionId
                ]);
                return Response::json([
                    'success' => true,
                    'data' => [],
                    'message' => 'Cart is empty',
                ], 200);
            }
        } catch (Exception $e) {
            Log::error("fetching  cart records. Message => {$e->getMessage()}, File => {$e->getFile()},  Line No => {$e->getLine()}, Error Code => {$e->getCode()}.");
            return sendError('Error', ['error' => 'An error is occured.'], 500);
        }
    }

    // Add product to cart
    public function add(AddToCartRequest $request)
    {
        try {
            // Check if Bearer token is provided
            if ($request->bearerToken()) {
                // Try to authenticate with Bearer token
                try {
                    $user = auth('api')->user();
                    if (!$user) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Invalid or expired Bearer token',
                            'error' => 'Token authentication failed - please login again to get a fresh token'
                        ], 401);
                    }
                    $userId = $user->id;
                } catch (Exception $e) {
                    Log::error('Bearer token validation error in add', ['error' => $e->getMessage()]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Bearer token validation failed',
                        'error' => 'Token signature verification failed - please login again',
                        'debug' => $e->getMessage()
                    ], 401);
                }
            } else {
                // No Bearer token, use session authentication
                $userId = auth()->id();
            }

            $sessionId = session()->getId();

            // Handle product_type conversion with fallback
            $productType = config('constants.product_types.' . $request->product_type);
            // If not found, try alternative mappings
            if (!$productType) {
                if ($request->product_type === 'mock_exam') {
                    $productType = config('constants.product_types.mock');
                } elseif ($request->product_type === 'extracted_paper') {
                    $productType = config('constants.product_types.papers');
                }
            }

            // Get price_id from product if not provided
            $priceId = $request->price_id;

            // Validate price_id format if provided (must start with 'price_' for Stripe)
            if ($priceId && strpos($priceId, 'price_') !== 0) {
                Log::warning("Invalid price_id format provided in request, will fetch from product", [
                    'price_id' => $priceId,
                    'product_id' => $request->product_id,
                    'product_type' => $productType
                ]);
                $priceId = null; // Treat invalid price_id as missing, will fetch from product
            }

            if (!$priceId) {
                if ($productType == config('constants.product_types.mock') || $productType == config('constants.product_types.mock_exam')) {
                    $mockExam = \App\Models\MockExam::find($request->product_id);
                    if ($mockExam) {
                        // Validate stripe_price_id - must start with 'price_' to be valid
                        if ($mockExam->stripe_price_id && strpos($mockExam->stripe_price_id, 'price_') === 0) {
                            $priceId = $mockExam->stripe_price_id;
                            Log::info("Using existing valid Stripe price_id for mock exam", [
                                'mock_exam_id' => $mockExam->id,
                                'price_id' => $priceId
                            ]);
                        } elseif ($mockExam->price && $mockExam->price > 0) {
                            // Mock exam has invalid or missing stripe_price_id, create a new one
                            Log::info("Mock exam has invalid or missing stripe_price_id, creating new one", [
                                'mock_exam_id' => $mockExam->id,
                                'current_stripe_price_id' => $mockExam->stripe_price_id,
                                'current_stripe_product_id' => $mockExam->stripe_product_id,
                                'mock_exam_price' => $mockExam->price
                            ]);
                            // Create Stripe price on the fly if it doesn't exist
                            try {
                                \Stripe\Stripe::setApiKey(config('constants.stripe_secret'));

                                // Validate and create or get Stripe product
                                // Check if stripe_product_id exists and is valid (must start with 'prod_')
                                $needsNewProduct = !$mockExam->stripe_product_id || strpos($mockExam->stripe_product_id, 'prod_') !== 0;

                                if ($needsNewProduct) {
                                    // Create new Stripe product
                                    $productData = [
                                        'name' => $mockExam->name,
                                    ];
                                    // Only add description if it's not empty
                                    if (!empty($mockExam->description)) {
                                        $productData['description'] = $mockExam->description;
                                    }
                                    $stripeProduct = \Stripe\Product::create($productData);
                                    $mockExam->update(['stripe_product_id' => $stripeProduct->id]);
                                    Log::info("Created new Stripe product for mock exam", [
                                        'mock_exam_id' => $mockExam->id,
                                        'stripe_product_id' => $stripeProduct->id
                                    ]);
                                } else {
                                    // Validate that the existing product exists in Stripe
                                    try {
                                        \Stripe\Product::retrieve($mockExam->stripe_product_id);
                                        Log::info("Using existing valid Stripe product for mock exam", [
                                            'mock_exam_id' => $mockExam->id,
                                            'stripe_product_id' => $mockExam->stripe_product_id
                                        ]);
                                    } catch (\Exception $e) {
                                        // Product doesn't exist in Stripe, create a new one
                                        Log::warning("Stripe product {$mockExam->stripe_product_id} not found, creating new one", [
                                            'mock_exam_id' => $mockExam->id,
                                            'error' => $e->getMessage()
                                        ]);
                                        $productData = [
                                            'name' => $mockExam->name,
                                        ];
                                        // Only add description if it's not empty
                                        if (!empty($mockExam->description)) {
                                            $productData['description'] = $mockExam->description;
                                        }
                                        $stripeProduct = \Stripe\Product::create($productData);
                                        $mockExam->update(['stripe_product_id' => $stripeProduct->id]);
                                        Log::info("Created new Stripe product after validation failed", [
                                            'mock_exam_id' => $mockExam->id,
                                            'stripe_product_id' => $stripeProduct->id
                                        ]);
                                    }
                                }

                                // Convert currency symbol to code if needed
                                $currency = $this->normalizeCurrency($mockExam->currency ?? 'gbp');

                                // Create Stripe price
                                $stripePrice = \Stripe\Price::create([
                                    'currency' => $currency,
                                    'unit_amount' => intval($mockExam->price * 100),
                                    'product' => $mockExam->stripe_product_id,
                                ]);

                                $mockExam->update(['stripe_price_id' => $stripePrice->id]);
                                $priceId = $stripePrice->id;
                                Log::info("Created Stripe price for mock exam when adding to cart", [
                                    'mock_exam_id' => $mockExam->id,
                                    'price_id' => $priceId
                                ]);
                            } catch (\Exception $e) {
                                Log::error("Failed to create Stripe price for mock exam {$mockExam->id}: {$e->getMessage()}", [
                                    'mock_exam_id' => $mockExam->id,
                                    'mock_exam_price' => $mockExam->price,
                                    'mock_exam_currency' => $mockExam->currency,
                                    'stripe_product_id' => $mockExam->stripe_product_id,
                                    'error' => $e->getMessage()
                                ]);
                                // Don't block cart addition - price will be created during checkout
                                $priceId = null;
                                Log::warning("Allowing cart addition without price_id - will create during checkout", [
                                    'mock_exam_id' => $mockExam->id
                                ]);
                            }
                        } else {
                            // Mock exam has no price or invalid price
                            Log::warning("Attempted to add mock exam to cart without valid price", [
                                'mock_exam_id' => $mockExam->id,
                                'mock_exam_price' => $mockExam->price,
                                'mock_exam_name' => $mockExam->name
                            ]);
                            return Response::json([
                                'success' => false,
                                'message' => 'This mock exam does not have a valid price configured. Please contact support.',
                                'error' => 'Mock exam missing valid price'
                            ], 400);
                        }
                    }
                } elseif ($productType == config('constants.product_types.papers') || $productType == config('constants.product_types.paper')) {
                    $paper = \App\Models\Paper::find($request->product_id);
                    if ($paper) {
                        // Validate stripe_price_id - must start with 'price_' to be valid
                        if ($paper->stripe_price_id && strpos($paper->stripe_price_id, 'price_') === 0) {
                            $priceId = $paper->stripe_price_id;
                            Log::info("Using existing valid Stripe price_id for paper", [
                                'paper_id' => $paper->id,
                                'price_id' => $priceId
                            ]);
                        } elseif ($paper->price && $paper->price > 0) {
                            // Paper has invalid or missing stripe_price_id, create a new one
                            Log::info("Paper has invalid or missing stripe_price_id, creating new one", [
                                'paper_id' => $paper->id,
                                'current_stripe_price_id' => $paper->stripe_price_id,
                                'current_stripe_product_id' => $paper->stripe_product_id,
                                'paper_price' => $paper->price
                            ]);
                            // Create Stripe price on the fly if it doesn't exist
                            try {
                                \Stripe\Stripe::setApiKey(config('constants.stripe_secret'));

                                // Validate and create or get Stripe product
                                // Check if stripe_product_id exists and is valid (must start with 'prod_')
                                $needsNewProduct = !$paper->stripe_product_id || strpos($paper->stripe_product_id, 'prod_') !== 0;

                                if ($needsNewProduct) {
                                    // Create new Stripe product
                                    $productData = [
                                        'name' => $paper->name,
                                    ];
                                    // Only add description if it's not empty
                                    if (!empty($paper->description)) {
                                        $productData['description'] = $paper->description;
                                    }
                                    $stripeProduct = \Stripe\Product::create($productData);
                                    $paper->update(['stripe_product_id' => $stripeProduct->id]);
                                    Log::info("Created new Stripe product for paper", [
                                        'paper_id' => $paper->id,
                                        'stripe_product_id' => $stripeProduct->id
                                    ]);
                                } else {
                                    // Validate that the existing product exists in Stripe
                                    try {
                                        \Stripe\Product::retrieve($paper->stripe_product_id);
                                        Log::info("Using existing valid Stripe product for paper", [
                                            'paper_id' => $paper->id,
                                            'stripe_product_id' => $paper->stripe_product_id
                                        ]);
                                    } catch (\Exception $e) {
                                        // Product doesn't exist in Stripe, create a new one
                                        Log::warning("Stripe product {$paper->stripe_product_id} not found, creating new one", [
                                            'paper_id' => $paper->id,
                                            'error' => $e->getMessage()
                                        ]);
                                        $productData = [
                                            'name' => $paper->name,
                                        ];
                                        // Only add description if it's not empty
                                        if (!empty($paper->description)) {
                                            $productData['description'] = $paper->description;
                                        }
                                        $stripeProduct = \Stripe\Product::create($productData);
                                        $paper->update(['stripe_product_id' => $stripeProduct->id]);
                                        Log::info("Created new Stripe product after validation failed", [
                                            'paper_id' => $paper->id,
                                            'stripe_product_id' => $stripeProduct->id
                                        ]);
                                    }
                                }

                                // Convert currency symbol to code if needed
                                $currency = $this->normalizeCurrency($paper->currency ?? 'gbp');

                                // Create Stripe price
                                $stripePrice = \Stripe\Price::create([
                                    'currency' => $currency,
                                    'unit_amount' => intval($paper->price * 100),
                                    'product' => $paper->stripe_product_id,
                                ]);

                                $paper->update(['stripe_price_id' => $stripePrice->id]);
                                $priceId = $stripePrice->id;
                                Log::info("Created Stripe price for paper when adding to cart", [
                                    'paper_id' => $paper->id,
                                    'price_id' => $priceId
                                ]);
                            } catch (\Exception $e) {
                                Log::error("Failed to create Stripe price for paper {$paper->id}: {$e->getMessage()}", [
                                    'paper_id' => $paper->id,
                                    'paper_price' => $paper->price,
                                    'paper_currency' => $paper->currency,
                                    'stripe_product_id' => $paper->stripe_product_id,
                                    'error' => $e->getMessage()
                                ]);
                                // Don't block cart addition - price will be created during checkout
                                $priceId = null;
                                Log::warning("Allowing cart addition without price_id - will create during checkout", [
                                    'paper_id' => $paper->id
                                ]);
                            }
                        } else {
                            // Paper has no price or invalid price
                            Log::warning("Attempted to add paper to cart without valid price", [
                                'paper_id' => $paper->id,
                                'paper_price' => $paper->price,
                                'paper_name' => $paper->name
                            ]);
                            return Response::json([
                                'success' => false,
                                'message' => 'This paper does not have a valid price configured. Please contact support.',
                                'error' => 'Paper missing valid price'
                            ], 400);
                        }
                    } else {
                        return Response::json([
                            'success' => false,
                            'message' => 'Paper not found.',
                            'error' => 'Invalid product_id'
                        ], 404);
                    }
                }
            }

            // Check if item already exists
            $existingCartItem = Cart::where('user_id', $userId)
                ->where(function ($query) use ($userId, $sessionId) {
                    if (!$userId) {
                        $query->where('session_id', $sessionId);
                    }
                })
                ->where('product_id', $request->product_id)
                ->where('product_type', $productType)
                ->where('price_id', $priceId ?? null) // Check price_id if exists
                ->first();

            if ($existingCartItem) {
                return Response::json([
                    'success' => true,
                    'message' => 'Product is already added to your cart.'
                ], 200);
            }

            // Validate price_id format if provided (must start with 'price_' for Stripe)
            if ($priceId && strpos($priceId, 'price_') !== 0) {
                Log::warning("Invalid price_id format provided, treating as missing", [
                    'price_id' => $priceId,
                    'product_id' => $request->product_id,
                    'product_type' => $productType
                ]);
                $priceId = null; // Treat invalid price_id as missing
            }

            // If no price_id found, log warning but allow cart addition for courses
            // For papers and mock exams, we'll try to create price later if needed
            if (!$priceId && $productType != config('constants.product_types.course')) {
                Log::warning("Adding product to cart without price_id - will be created later if needed", [
                    'product_id' => $request->product_id,
                    'product_type' => $productType
                ]);
                // Don't block cart addition - price can be created during checkout
            }

            // If not exists, add to cart
            $cartItem = Cart::create([
                'user_id' => $userId,
                'session_id' => $userId ? null : $sessionId,
                'product_id' => $request->product_id,
                'product_type' => $productType,
                'quantity' => $request->quantity,
                'price_id' => $priceId, // Store price_id (from request or product)
            ]);

            return Response::json([
                'success' => true,
                'message' => 'Product added to cart!'
            ], 200);
        } catch (Exception $e) {
            Log::error("Error adding product to cart. Message => {$e->getMessage()}, File => {$e->getFile()},  Line No => {$e->getLine()}, Error Code => {$e->getCode()}.");
            return response()->json(['error' => 'An error occurred while adding product to cart'], 500);
        }
    }


    // Update cart item quantity
    public function update(UpdateCartRequest $request, Cart $cart)
    {
        try {
            // Check if Bearer token is provided
            if ($request->bearerToken()) {
                // Try to authenticate with Bearer token
                try {
                    $user = auth('api')->user();
                    if (!$user) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Invalid or expired Bearer token',
                            'error' => 'Token authentication failed - please login again to get a fresh token'
                        ], 401);
                    }
                    $userId = $user->id;
                } catch (Exception $e) {
                    Log::error('Bearer token validation error in update', ['error' => $e->getMessage()]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Bearer token validation failed',
                        'error' => 'Token signature verification failed - please login again',
                        'debug' => $e->getMessage()
                    ], 401);
                }
            } else {
                // No Bearer token, use session authentication
                $userId = auth()->id();
            }

            $sessionId = session()->getId();

            // Verify ownership
            $isOwner = $userId
                ? $cart->user_id === $userId
                : $cart->session_id === $sessionId;

            if (!$isOwner) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to cart item.',
                ], 403);
            }

            $cart->update(['quantity' => $request->quantity]);

            return response()->json([
                'success' => true,
                'data' => $cart,
                'message' => 'Cart item quantity updated successfully.',
            ]);
        } catch (Exception $e) {
            Log::error("Error updating cart item: {$e->getMessage()} in {$e->getFile()} at line {$e->getLine()}");
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating the cart item.',
            ], 500);
        }
    }

    /**
     * Normalize currency symbol/code to Stripe-compatible format
     * 
     * @param string $currency
     * @return string
     */
    private function normalizeCurrency($currency)
    {
        if (empty($currency)) {
            return 'gbp';
        }

        $currency = trim($currency);

        // Map currency symbols to codes (check before lowercasing)
        $currencyMap = [
            '€' => 'eur',
            '£' => 'gbp',
            '$' => 'usd',
            '¥' => 'jpy',
            '₹' => 'inr',
        ];

        // If it's a symbol, convert to code
        if (isset($currencyMap[$currency])) {
            return $currencyMap[$currency];
        }

        // Convert to lowercase for code checking
        $currencyLower = strtolower($currency);

        // If it's already a valid 3-letter code, return as is
        if (strlen($currencyLower) === 3 && ctype_alpha($currencyLower)) {
            return $currencyLower;
        }

        // Default fallback
        return 'gbp';
    }

    // Remove from cart
    public function remove(RemoveFromCartRequest $request, Cart $cart)
    {
        try {
            // Check if Bearer token is provided
            if ($request->bearerToken()) {
                // Try to authenticate with Bearer token
                try {
                    $user = auth('api')->user();
                    if (!$user) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Invalid or expired Bearer token',
                            'error' => 'Token authentication failed - please login again to get a fresh token'
                        ], 401);
                    }
                    $userId = $user->id;
                } catch (Exception $e) {
                    Log::error('Bearer token validation error in remove', ['error' => $e->getMessage()]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Bearer token validation failed',
                        'error' => 'Token signature verification failed - please login again',
                        'debug' => $e->getMessage()
                    ], 401);
                }
            } else {
                // No Bearer token, use session authentication
                $userId = auth()->id();
            }

            $sessionId = session()->getId();

            // Check if the authenticated user or session owns the cart item
            $isOwner = $userId
                ? $cart->user_id === $userId
                : $cart->session_id === $sessionId;

            if (!$isOwner) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to remove cart item.',
                ], 403);
            }

            $cart->delete();

            return response()->json([
                'success' => true,
                'message' => 'Cart item removed successfully.',
            ], 200);
        } catch (Exception $e) {
            Log::error("Error removing cart item: {$e->getMessage()} in {$e->getFile()} at line {$e->getLine()}");
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while removing the cart item.',
            ], 500);
        }
    }
}
