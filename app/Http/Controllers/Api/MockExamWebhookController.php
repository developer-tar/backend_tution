<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MockExam;
use App\Models\MockExamPurchase;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierWebhookController;

class MockExamWebhookController extends CashierWebhookController
{
    /**
     * Handle checkout session completed
     */
    public function handleCheckoutSessionCompleted(array $payload)
    {
        try {
            $session = $payload['data']['object'];

            // Check if this is a mock exam purchase
            if (!isset($session['metadata']['type']) || $session['metadata']['type'] !== 'mock_exam_purchase') {
                return $this->successMethod();
            }

            $mockExamId = $session['metadata']['mock_exam_id'] ?? null;
            $userId = $session['metadata']['user_id'] ?? null;

            if (!$mockExamId || !$userId) {
                Log::error('Mock exam webhook: Missing metadata', $session['metadata']);
                return $this->successMethod();
            }

            $user = User::find($userId);
            $mockExam = MockExam::find($mockExamId);

            if (!$user || !$mockExam) {
                Log::error('Mock exam webhook: User or MockExam not found', [
                    'user_id' => $userId,
                    'mock_exam_id' => $mockExamId
                ]);
                return $this->successMethod();
            }

            // Check if purchase already exists
            $existingPurchase = MockExamPurchase::where('user_id', $userId)
                ->where('mock_exam_id', $mockExamId)
                ->where('stripe_session_id', $session['id'])
                ->first();

            if ($existingPurchase) {
                // Update payment status if needed
                if ($existingPurchase->payment_status !== config('constants.stripe_payment_status.PAID')) {
                    $existingPurchase->update([
                        'payment_status' => config('constants.stripe_payment_status.PAID'),
                        'purchased_at' => now(),
                    ]);
                }
                Log::info('Mock exam purchase already exists', ['purchase_id' => $existingPurchase->id]);
                return $this->successMethod();
            }

            // Create purchase record
            $purchase = MockExamPurchase::create([
                'user_id' => $userId,
                'mock_exam_id' => $mockExamId,
                'student_id' => $session['metadata']['student_id'] ?? null, // For parent purchases
                'stripe_session_id' => $session['id'],
                'transaction_id' => $session['payment_intent'] ?? null,
                'amount' => ($session['amount_total'] ?? 0) / 100, // Convert from cents
                'currency' => $session['currency'] ?? 'gbp',
                'purchased_at' => now(),
                'total_marks' => $mockExam->total_marks,
                'status' => config('constants.mock_exam_purchase_status.NOT_STARTED'),
                'purchased_by' => $session['metadata']['purchased_by'] ?? 'student', // parent or student
                'payment_status' => config('constants.stripe_payment_status.PAID'), // Add payment status
            ]);

            Log::info('Mock exam purchase created successfully', [
                'purchase_id' => $purchase->id,
                'user_id' => $userId,
                'mock_exam_id' => $mockExamId,
                'amount' => $purchase->amount,
            ]);

            return $this->successMethod();

        } catch (Exception $e) {
            Log::error('Mock exam webhook error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'payload' => $payload
            ]);
            
            return $this->successMethod();
        }
    }

    /**
     * Handle payment failed
     */
    public function handlePaymentIntentPaymentFailed(array $payload)
    {
        try {
            $paymentIntent = $payload['data']['object'];
            
            Log::warning('Mock exam payment failed', [
                'payment_intent_id' => $paymentIntent['id'],
                'metadata' => $paymentIntent['metadata'] ?? []
            ]);

            return $this->successMethod();

        } catch (Exception $e) {
            Log::error('Mock exam payment failed webhook error: ' . $e->getMessage());
            return $this->successMethod();
        }
    }

    /**
     * Handle refund created
     */
    public function handleChargeDisputeCreated(array $payload)
    {
        try {
            $dispute = $payload['data']['object'];
            $chargeId = $dispute['charge'];

            // Find purchase by charge and mark as disputed
            $purchase = MockExamPurchase::where('transaction_id', $chargeId)->first();
            
            if ($purchase) {
                Log::warning('Mock exam purchase disputed', [
                    'purchase_id' => $purchase->id,
                    'charge_id' => $chargeId,
                    'dispute_reason' => $dispute['reason'] ?? 'unknown'
                ]);
            }

            return $this->successMethod();

        } catch (Exception $e) {
            Log::error('Mock exam dispute webhook error: ' . $e->getMessage());
            return $this->successMethod();
        }
    }
}
