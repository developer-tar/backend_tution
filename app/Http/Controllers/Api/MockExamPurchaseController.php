<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MockExam;
use App\Models\MockExamPurchase;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Cashier;

class MockExamPurchaseController extends Controller
{
    /**
     * Parent checkout for mock exam (similar to course checkout)
     */
    public function parentCheckout(Request $request)
    {
        $request->validate([
            'mock_exam_id' => 'required|exists:mock_exams,id',
            'student_id' => 'nullable|exists:users,id', // Optional: for which student
        ]);

        try {
            $user = Auth::user(); // Parent user
            $mockExam = MockExam::with('format:id,name')->findOrFail($request->mock_exam_id);

            // Check if already purchased for this student/parent
            $existingPurchase = MockExamPurchase::where('user_id', $user->id)
                ->where('mock_exam_id', $mockExam->id)
                ->when($request->student_id, fn($q) => $q->where('student_id', $request->student_id))
                ->first();

            if ($existingPurchase) {
                return sendError('This mock exam has already been purchased', [], 400);
            }

            if (!$mockExam->stripe_price_id) {
                return sendError('This mock exam is not available for purchase', [], 400);
            }

            $frontendUrl = config('app.frontend_url', 'http://localhost:3000');

            // Create one-time checkout session using Cashier (same as course pattern)
            $checkout = $user->checkout($mockExam->stripe_price_id, [
                'success_url' => $frontendUrl . '/parent/mock-exam/payment-success?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $frontendUrl . '/parent/mock-exam/payment-cancel',
                'payment_method_types' => ['card'],
                'metadata' => [
                    'mock_exam_id' => $mockExam->id,
                    'user_id' => $user->id, // Parent ID
                    'student_id' => $request->student_id ?? null, // Student ID (optional)
                    'type' => 'mock_exam_purchase',
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
            Log::error("Failed to create parent mock exam checkout: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
            return sendError('An error occurred while processing your request', [], 500);
        }
    }

    /**
     * Purchase mock exam (one-time payment) - Original method for students
     */
    public function purchaseMockExam(Request $request)
    {
        $request->validate([
            'mock_exam_id' => 'required|exists:mock_exams,id',
        ]);

        try {
            $user = Auth::user();
            $mockExam = MockExam::with('format:id,name')->findOrFail($request->mock_exam_id);

            // Check if already purchased
            $existingPurchase = MockExamPurchase::where('user_id', $user->id)
                ->where('mock_exam_id', $mockExam->id)
                ->first();

            if ($existingPurchase) {
                return sendError('You have already purchased this mock exam', [], 400);
            }

            if (!$mockExam->stripe_price_id) {
                return sendError('This mock exam is not available for purchase', [], 400);
            }

            $frontendUrl = config('app.frontend_url', 'http://localhost:3000');

            // Create one-time checkout session using Cashier
            $checkout = $user->checkout($mockExam->stripe_price_id, [
                'success_url' => $frontendUrl . '/mock-exam/payment-success?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $frontendUrl . '/mock-exam/payment-cancel',
                'payment_method_types' => ['card'],
                'metadata' => [
                    'mock_exam_id' => $mockExam->id,
                    'user_id' => $user->id, // Student ID
                    'student_id' => null, // Student buying for themselves
                    'type' => 'mock_exam_purchase',
                    'purchased_by' => 'student',
                ],
            ]);
            return sendResponse([
                'checkout_url' => $checkout->url,
                'session_id' => $checkout->id,
            ], 'Redirecting to payment...');
            

        } catch (Exception $e) {
            return errorLog("Failed to create mock exam checkout: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
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

            $mockExamId = $session->metadata->mock_exam_id ?? null;
            $userId = $session->metadata->user_id ?? null;

            if (!$mockExamId || !$userId) {
                return sendError('Invalid session metadata', [], 400);
            }

            // Check if purchase record already exists
            $purchase = MockExamPurchase::where('user_id', $userId)
                ->where('mock_exam_id', $mockExamId)
                ->where('stripe_session_id', $session->id)
                ->first();

            if (!$purchase) {
                return sendError('Purchase record not found', [], 404);
            }
            return sendResponse([
                'purchase_id' => $purchase->id,
                'mock_exam_id' => $mockExamId,
            ], 'Payment verified successfully.');
            
        } catch (Exception $e) {
            return errorLog("Failed to verify payment: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
           
        }
    }

    /**
     * Get user's purchased mock exams
     */
    public function myPurchases()
    {
        try {
            $user = Auth::user();

            $purchases = MockExamPurchase::with([
                'mockExam:id,name,duration_minutes,total_marks',
                'mockExam.format:id,name',
                'mockExam.category:id,name',
                'student:id,full_name,email', // For parent purchases
            ])
                ->where('user_id', $user->id)
                ->orderBy('purchased_at', 'desc')
                ->get()
                ->map(function ($purchase) {
                    return [
                        'purchase_id' => $purchase->id,
                        'mock_exam_id' => $purchase->mock_exam_id,
                        'exam_name' => $purchase->mockExam->name,
                        'category' => $purchase->mockExam->category?->name,
                        'format' => $purchase->mockExam->format?->name,
                        'duration_minutes' => $purchase->mockExam->duration_minutes,
                        'total_marks' => $purchase->total_marks,
                        'score' => $purchase->score,
                        'status' => $purchase->status,
                        'purchased_at' => $purchase->purchased_at,
                        'started_at' => $purchase->started_at,
                        'completed_at' => $purchase->completed_at,
                        'purchased_by' => $purchase->purchased_by,
                        'student_name' => $purchase->student?->full_name, // For parent purchases
                        'student_email' => $purchase->student?->email,
                        'image' => $purchase->mockExam->getFirstMediaUrl('mock_exam_image') ?: config('constants.dummy_image'),
                    ];
                });

            return sendResponse($purchases, 'Purchased mock exams fetched successfully');

        } catch (Exception $e) {
            return errorLog("Failed to fetch user purchases: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }
}
