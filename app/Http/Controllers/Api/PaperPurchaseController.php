<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Paper;
use App\Models\PaperPurchase;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Cashier;

class PaperPurchaseController extends Controller
{
    /**
     * Parent checkout for paper (similar to course checkout)
     */
    public function parentCheckout(Request $request)
    {
        $request->validate([
            'paper_id' => 'required|exists:papers,id',
            'student_id' => 'nullable|exists:users,id', // Optional: for which student
        ]);

        try {
            $user = Auth::user(); // Parent user
            $paper = Paper::with('format:id,name')->findOrFail($request->paper_id);

            // Check if already purchased for this student/parent
            $existingPurchase = PaperPurchase::where('user_id', $user->id)
                ->where('paper_id', $paper->id)
                ->when($request->student_id, fn($q) => $q->where('student_id', $request->student_id))
                ->first();

            if ($existingPurchase) {
                return sendError('This paper has already been purchased', [], 400);
            }

            if (!$paper->stripe_price_id) {
                return sendError('This paper is not available for purchase', [], 400);
            }

            $frontendUrl = config('app.frontend_url', 'http://localhost:3000');

            // Create one-time checkout session using Cashier (same as course pattern)
            $checkout = $user->checkout($paper->stripe_price_id, [
                'success_url' => $frontendUrl . '/parent/paper/payment-success?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $frontendUrl . '/parent/paper/payment-cancel',
                'metadata' => [
                    'paper_id' => $paper->id,
                    'user_id' => $user->id, // Parent ID
                    'student_id' => $request->student_id ?? null, // Student ID (optional)
                    'type' => 'paper_purchase',
                    'purchased_by' => 'parent',
                ],
            ]);

            return response()->json([
                'success' => true,
                'checkout_url' => $checkout->url,
                'session_id' => $checkout->id,
                'message' => 'Redirecting to payment...',
            ]);

        } catch (Exception $e) {
            Log::error("Failed to create parent paper checkout: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
            return sendError('An error occurred while processing your request', [], 500);
        }
    }

    /**
     * Purchase paper (one-time payment) - Original method for students
     */
    public function purchasePaper(Request $request)
    {
        $request->validate([
            'paper_id' => 'required|exists:papers,id',
        ]);

        try {
            $user = Auth::user();
            $paper = Paper::with('format:id,name')->findOrFail($request->paper_id);

            // Check if already purchased
            $existingPurchase = PaperPurchase::where('user_id', $user->id)
                ->where('paper_id', $paper->id)
                ->first();

            if ($existingPurchase) {
                return sendError('You have already purchased this paper', [], 400);
            }

            if (!$paper->stripe_price_id) {
                return sendError('This paper is not available for purchase', [], 400);
            }

            $frontendUrl = config('app.frontend_url', 'http://localhost:3000');

            // Create one-time checkout session using Cashier
            $checkout = $user->checkout($paper->stripe_price_id, [
                'success_url' => $frontendUrl . '/paper/payment-success?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $frontendUrl . '/paper/payment-cancel',
                'metadata' => [
                    'paper_id' => $paper->id,
                    'user_id' => $user->id, // Student ID
                    'student_id' => null, // Student buying for themselves
                    'type' => 'paper_purchase',
                    'purchased_by' => 'student',
                ],
            ]);
            return sendResponse([
                'checkout_url' => $checkout->url,
                'session_id' => $checkout->id,
            ], 'Redirecting to payment...');
            

        } catch (Exception $e) {
            return errorLog("Failed to create paper checkout: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    /**
     * Verify payment success
     */
    public function verifyPayment(Request $request)
    {
        $request->validate([
            'session_id' => 'required|string',
        ]);

        try {
            $stripe = Cashier::stripe();
            $session = $stripe->checkout->sessions->retrieve($request->session_id);

            if ($session->payment_status !== 'paid') {
                return sendError('Payment not completed', [], 400);
            }

            $paperId = $session->metadata->paper_id ?? null;
            $userId = $session->metadata->user_id ?? null;

            if (!$paperId || !$userId) {
                return sendError('Invalid session metadata', [], 400);
            }

            // Check if purchase record already exists
            $purchase = PaperPurchase::where('user_id', $userId)
                ->where('paper_id', $paperId)
                ->where('stripe_session_id', $session->id)
                ->first();

            if (!$purchase) {
                return sendError('Purchase record not found', [], 404);
            }
            return sendResponse([
                'purchase_id' => $purchase->id,
                'paper_id' => $paperId,
            ], 'Payment verified successfully.');
            
        } catch (Exception $e) {
            return errorLog("Failed to verify payment: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
           
        }
    }

    /**
     * Get user's purchased papers
     */
    public function myPurchases()
    {
        try {
            $user = Auth::user();

            $purchases = PaperPurchase::with([
                'paper:id,name,duration_minutes,total_marks',
                'paper.format:id,name',
                'paper.category:id,name',
                'student:id,full_name,email', // For parent purchases
            ])
                ->where('user_id', $user->id)
                ->orderBy('purchased_at', 'desc')
                ->get()
                ->map(function ($purchase) {
                    return [
                        'purchase_id' => $purchase->id,
                        'paper_id' => $purchase->paper_id,
                        'paper_name' => $purchase->paper->name,
                        'category' => $purchase->paper->category?->name,
                        'format' => $purchase->paper->format?->name,
                        'duration_minutes' => $purchase->paper->duration_minutes,
                        'total_marks' => $purchase->total_marks,
                        'score' => $purchase->score,
                        'status' => $purchase->status,
                        'purchased_at' => $purchase->purchased_at,
                        'started_at' => $purchase->started_at,
                        'completed_at' => $purchase->completed_at,
                        'purchased_by' => $purchase->purchased_by,
                        'student_name' => $purchase->student?->full_name, // For parent purchases
                        'student_email' => $purchase->student?->email,
                        'image' => $purchase->paper->getFirstMediaUrl('paper_image') ?: config('constants.dummy_image'),
                    ];
                });

            return sendResponse($purchases, 'Purchased papers fetched successfully');

        } catch (Exception $e) {
            return errorLog("Failed to fetch user purchases: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }
}







