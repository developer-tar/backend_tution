<?php

namespace App\Http\Controllers\Api;

use App\Models\MockExam;
use App\Models\MockExamPurchase;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierWebhookController;

class CommonWebhookController extends CashierWebhookController
{
    /**
     * Handle checkout session completed for both subscriptions and one-time payments
     */
    public function handleCheckoutSessionCompleted(array $payload)
    {
        try {
            $session = $payload['data']['object'];
            
            Log::info('Webhook received: checkout.session.completed', [
                'session_id' => $session['id'],
                'payment_status' => $session['payment_status'],
                'mode' => $session['mode'], // 'subscription' or 'payment'
                'metadata' => $session['metadata'] ?? []
            ]);

            // Handle based on session mode
            if ($session['mode'] === 'payment') {
                return $this->handleOneTimePayment($session);
            }

            Log::warning('Unknown session mode', ['mode' => $session['mode']]);
            return $this->successMethod();

        } catch (Exception $e) {
            Log::error('Webhook error in handleCheckoutSessionCompleted: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'payload' => $payload
            ]);
            
            return $this->successMethod();
        }
    }

    /**
     * Handle one-time payment (mock exams)
     */
    protected function handleOneTimePayment(array $session)
    {
        try {
            // Check if this is a mock exam purchase
            if (!isset($session['metadata']['type']) || $session['metadata']['type'] !== 'mock_exam_purchase') {
                Log::info('One-time payment but not mock exam purchase', ['metadata' => $session['metadata']]);
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
            ]);

            Log::info('Mock exam purchase created successfully', [
                'purchase_id' => $purchase->id,
                'user_id' => $userId,
                'mock_exam_id' => $mockExamId,
                'amount' => $purchase->amount,
                'purchased_by' => $purchase->purchased_by,
            ]);

            return $this->successMethod();

        } catch (Exception $e) {
            Log::error('One-time payment error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'session' => $session
            ]);
            
            return $this->successMethod();
        }
    }
    protected function handleCheckoutSessionExpired(array $payload)
    {
        try {
            $session = $payload['data']['object'];
            
            Log::info('Webhook received: checkout.session.expired', [
                'session_id' => $session['id'],
                'payment_status' => $session['payment_status'],
                'mode' => $session['mode'], // 'subscription' or 'payment'
                'metadata' => $session['metadata'] ?? []
            ]);

            // Currently, no specific action is needed on session expiration
            return $this->successMethod();

        } catch (Exception $e) {
            Log::error('Webhook error in handleCheckoutSessionExpired: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'payload' => $payload
            ]);
            
            return $this->successMethod();
        }
    }

   
}
