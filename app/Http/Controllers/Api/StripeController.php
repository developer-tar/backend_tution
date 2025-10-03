<?php

namespace App\Http\Controllers\Api;

use App\Models\MockExam;
use App\Models\MockExamPurchase;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Http\Controllers\WebhookController;

class StripeController extends WebhookController
{
    /**
     * Handle webhook requests
     */
    public function handleWebhook(Request $request)
    {
        return parent::handleWebhook($request);
    }

    /**
     * Handle checkout session completed for both subscriptions and one-time payments
     */
    public function handleCheckoutSessionCompleted($payload)
    {
        try {
            $session = $payload['data']['object'];
            
            Log::info('Stripe Webhook: checkout.session.completed', [
                'session_id' => $session['id'],
                'payment_status' => $session['payment_status'],
                'mode' => $session['mode'], // 'subscription' or 'payment'
                'metadata' => $session['metadata'] ?? []
            ]);

            // Handle based on session mode
            // if ($session['mode'] === 'subscription') {
            //     // Let Cashier handle subscription automatically
            //     Log::info('Processing subscription checkout', ['session_id' => $session['id']]);
            //     return parent::handleCheckoutSessionCompleted($payload);
                
            // } 
            if ($session['mode'] === 'payment') {
                // Handle one-time payment
                $type = $session['metadata']['type'] ?? 'unknown';
                
                if ($type === 'mock_exam_purchase') {
                    return $this->handleMockExamPurchase($session);
                } elseif ($type === 'cart_checkout') {
                    return $this->handleCartCheckout($session);
                }
                
                Log::warning('Unknown payment type', ['type' => $type]);
                return $this->successMethod();
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
     * Handle mock exam purchase (one-time payment)
     */
    protected function handleMockExamPurchase(array $session)
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
            $existingPurchase = MockExamPurchase::where('stripe_session_id', $session['id'])
                ->first();

            if ($existingPurchase) {
                Log::info('Mock exam purchase already exists', ['purchase_id' => $existingPurchase->id]);
                return $this->successMethod();
            }

            $purchasedBy = $session['metadata']['purchased_by'] ?? 'student';
            $studentId = $session['metadata']['student_id'] ?? null;

            // Determine who gets the purchase assigned to
            $assignedUserId = null;
            if ($purchasedBy === 'parent' && $studentId) {
                // Parent bought for student - assign to student
                $assignedUserId = $studentId;
            } else {
                // Student bought for themselves - assign to student
                $assignedUserId = $userId;
            }

            // Create purchase record in database
            $purchase = MockExamPurchase::create([
                'user_id' => $assignedUserId, // Who can access the exam
                'mock_exam_id' => $mockExamId,
                'student_id' => $studentId, // For parent purchases tracking
                'stripe_session_id' => $session['id'],
                'transaction_id' => $session['payment_intent'] ?? null,
                'amount' => ($session['amount_total'] ?? 0) / 100, // Convert from cents
                'currency' => $session['currency'] ?? 'gbp',
                'purchased_at' => now(),
                'total_marks' => $mockExam->total_marks,
                'status' => config('constants.mock_exam_purchase_status.NOT_STARTED'),
                'purchased_by' => $purchasedBy, // Who paid for it
            ]);

            Log::info('Mock exam purchase created successfully', [
                'purchase_id' => $purchase->id,
                'assigned_to_user_id' => $assignedUserId, // Who can access
                'paid_by_user_id' => $userId, // Who paid
                'mock_exam_id' => $mockExamId,
                'amount' => $purchase->amount,
                'purchased_by' => $purchase->purchased_by,
                'student_id' => $purchase->student_id,
            ]);

            return $this->successMethod();

        } catch (Exception $e) {
            Log::error('Mock exam purchase error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'session' => $session
            ]);
            
            return $this->successMethod();
        }
    }

    /**
     * Handle cart checkout (multiple one-time items)
     */
    protected function handleCartCheckout(array $session)
    {
        try {
            $userId = $session['metadata']['user_id'] ?? null;
            $purchasedBy = $session['metadata']['purchased_by'] ?? config('constant.roles.PARENT');

            if (!$userId) {
                Log::error('Cart checkout webhook: Missing user_id', $session['metadata']);
                return $this->successMethod();
            }

            Log::info('Cart checkout completed', [
                'session_id' => $session['id'],
                'user_id' => $userId,
                'purchased_by' => $purchasedBy,
                'amount_total' => $session['amount_total'] ?? 0,
            ]);

            // For cart checkout, let Cashier handle it automatically
            // This is for courses or other cart items, not mock exams
            // Mock exams should use direct purchase, not cart

            return $this->successMethod();

        } catch (Exception $e) {
            Log::error('Cart checkout error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'session' => $session
            ]);
            
            return $this->successMethod();
        }
    }

    /**
     * Handle invoice payment succeeded (for subscriptions)
     */
    // public function handleInvoicePaymentSucceeded($payload)
    // {
    //     try {
    //         $invoice = $payload['data']['object'];
            
    //         Log::info('Invoice payment succeeded', [
    //             'invoice_id' => $invoice['id'],
    //             'subscription_id' => $invoice['subscription'] ?? null,
    //             'amount_paid' => $invoice['amount_paid'] ?? 0,
    //             'customer' => $invoice['customer'] ?? null
    //         ]);

    //         // Let Cashier handle this automatically
    //         return parent::handleInvoicePaymentSucceeded($payload);

    //     } catch (Exception $e) {
    //         Log::error('Invoice payment succeeded webhook error: ' . $e->getMessage());
    //         return $this->successMethod();
    //     }
    // }

    /**
     * Handle payment failed
     */
    public function handlePaymentIntentPaymentFailed($payload)
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
     * Handle subscription deleted/cancelled
     */
    // public function handleCustomerSubscriptionDeleted($payload)
    // {
    //     try {
    //         $subscription = $payload['data']['object'];
            
    //         Log::info('Subscription cancelled', [
    //             'subscription_id' => $subscription['id'],
    //             'customer' => $subscription['customer'] ?? null,
    //             'status' => $subscription['status'] ?? null
    //         ]);

    //         // Let Cashier handle this automatically
    //         return parent::handleCustomerSubscriptionDeleted($payload);

    //     } catch (Exception $e) {
    //         Log::error('Subscription deleted webhook error: ' . $e->getMessage());
    //         return $this->successMethod();
    //     }
    // }

    /**
     * Handle dispute created
     */
    public function handleChargeDisputeCreated($payload)
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
