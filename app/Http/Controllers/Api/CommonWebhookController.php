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
            if ($session['mode'] === 'subscription') {
                return $this->handleSubscriptionCheckout($session);
            } elseif ($session['mode'] === 'payment') {
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
     * Handle subscription checkout (courses)
     */
    protected function handleSubscriptionCheckout(array $session)
    {
        try {
            Log::info('Processing subscription checkout', ['session_id' => $session['id']]);
            
            // Let Cashier handle subscription creation automatically
            // This will create subscription records in the database
            
            return $this->successMethod();

        } catch (Exception $e) {
            Log::error('Subscription checkout error: ' . $e->getMessage());
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

    /**
     * Handle payment failed
     */
    public function handlePaymentIntentPaymentFailed(array $payload)
    {
        try {
            $paymentIntent = $payload['data']['object'];
            
            Log::warning('Payment failed', [
                'payment_intent_id' => $paymentIntent['id'],
                'metadata' => $paymentIntent['metadata'] ?? [],
                'last_payment_error' => $paymentIntent['last_payment_error'] ?? null
            ]);

            return $this->successMethod();

        } catch (Exception $e) {
            Log::error('Payment failed webhook error: ' . $e->getMessage());
            return $this->successMethod();
        }
    }

    /**
     * Handle invoice payment succeeded (for subscriptions)
     */
    public function handleInvoicePaymentSucceeded(array $payload)
    {
        try {
            $invoice = $payload['data']['object'];
            
            Log::info('Invoice payment succeeded', [
                'invoice_id' => $invoice['id'],
                'subscription_id' => $invoice['subscription'] ?? null,
                'amount_paid' => $invoice['amount_paid'] ?? 0,
                'customer' => $invoice['customer'] ?? null
            ]);

            // Let Cashier handle this automatically
            return $this->successMethod();

        } catch (Exception $e) {
            Log::error('Invoice payment succeeded webhook error: ' . $e->getMessage());
            return $this->successMethod();
        }
    }

    /**
     * Handle subscription deleted/cancelled
     */
    public function handleCustomerSubscriptionDeleted(array $payload)
    {
        try {
            $subscription = $payload['data']['object'];
            
            Log::info('Subscription cancelled', [
                'subscription_id' => $subscription['id'],
                'customer' => $subscription['customer'] ?? null,
                'status' => $subscription['status'] ?? null
            ]);

            // Let Cashier handle this automatically
            return $this->successMethod();

        } catch (Exception $e) {
            Log::error('Subscription deleted webhook error: ' . $e->getMessage());
            return $this->successMethod();
        }
    }

    /**
     * Handle dispute created
     */
    public function handleChargeDisputeCreated(array $payload)
    {
        try {
            $dispute = $payload['data']['object'];
            $chargeId = $dispute['charge'];

            Log::warning('Dispute created', [
                'dispute_id' => $dispute['id'],
                'charge_id' => $chargeId,
                'reason' => $dispute['reason'] ?? 'unknown',
                'amount' => $dispute['amount'] ?? 0
            ]);

            // Find mock exam purchase by charge and log dispute
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
            Log::error('Dispute webhook error: ' . $e->getMessage());
            return $this->successMethod();
        }
    }
}
