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
            $userId = auth()->id();
            $sessionId = session()->getId();

            $cartItems = Cart::with('course')
                ->where(function ($q) use ($userId, $sessionId) {
                    $userId ? $q->where('user_id', $userId) : $q->where('session_id', $sessionId);
                })
                ->get();

            if ($cartItems->isNotEmpty()) {
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
            $userId = auth()->id();
            $sessionId = session()->getId();
            // Add or update the cart item
            $cartItem = Cart::updateOrCreate(
                [
                    'user_id' => $userId,
                    'session_id' => $userId ? null : $sessionId,
                    'product_id' => $request->product_id,
                    'product_type' => config('constants.product_types.' . $request->product_type),
                ],
                [
                    'quantity' => \DB::raw("quantity + {$request->quantity}"),
                ]
            );

            return Response::json([
                'success' => true,
                'message' => $cartItem->wasRecentlyCreated
                    ? 'Product added to cart!!'
                    : 'Product quantity updated in cart!!'
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
            $userId = auth()->id();
            $sessionId = session()->getId();
            dd($sessionId);
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
            $userId = auth()->id();
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

