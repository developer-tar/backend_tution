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

            $cartItems = Cart::with(['price'])
                ->where(function ($q) use ($userId, $sessionId) {
                    $userId ? $q->where('user_id', $userId) : $q->where('session_id', $sessionId);
                })
                ->get();
            
            // Load relationships conditionally based on product_type
            $cartItems->load([
                'course' => function ($query) {
                    // Only load if product_type is course
                },
                'mockExam' => function ($query) {
                    // Only load if product_type is mock
                },
                'paper.format' => function ($query) {
                    // Load paper with format relationship for papers
                }
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
                    elseif ($productType == config('constants.product_types.mock') && $item->mockExam) {
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
                    elseif (($productType == config('constants.product_types.papers') || $productType == config('constants.product_types.paper')) && $item->paper) {
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
                return sendError('Error', ['error' => 'No Record found'], 404);
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

            $productType = config('constants.product_types.' . $request->product_type);

            // Check if item already exists
            $existingCartItem = Cart::where('user_id', $userId)
                ->where(function ($query) use ($userId, $sessionId) {
                    if (!$userId) {
                        $query->where('session_id', $sessionId);
                    }
                })
                ->where('product_id', $request->product_id)
                ->where('product_type', $productType)
                ->where('price_id', $request->price_id ?? null) // Check price_id if exists
                ->first();

            if ($existingCartItem) {
                return Response::json([
                    'success' => true,
                    'message' => 'Product is already added to your cart.'
                ], 200);
            }


            // If not exists, add to cart
            $cartItem = Cart::create([
                'user_id' => $userId,
                'session_id' => $userId ? null : $sessionId,
                'product_id' => $request->product_id,
                'product_type' => $productType,
                'quantity' => $request->quantity,
                'price_id' => $request->price_id ?? null, // Store price_id if exists
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
