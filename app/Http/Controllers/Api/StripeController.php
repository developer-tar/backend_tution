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
     * Stripe webhook entrypoint
     */
    public function handleWebhook(Request $request)
    {
        return parent::handleWebhook($request);
    }

    /**
     * Checkout session completed (success / failed)
     */
    public function handleCheckoutSessionCompleted(array $payload)
    {
        try {
            $session = $payload['data']['object'];
            
            Log::info('Webhook received: checkout.session.completed', [
                'session_id' => $session['id'],
                'payment_status' => $session['payment_status'],
                'mode' => $session['mode'],
                'metadata' => $session['metadata'] ?? []
            ]);

            // agar payment success nahi hai to skip ya fail mark karo
            if (($session['payment_status'] ?? '') !== 'paid') {
                Log::warning('Payment did not succeed', [
                    'session_id' => $session['id'],
                    'status' => $session['payment_status']
                ]);
                return $this->handleFailedPayment($session);
            }

            if ($session['mode'] === 'payment') {
                return $this->handleMockExamPurchase($session);
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
     * Checkout session expire (user did not pay)
     */
    public function handleCheckoutSessionExpired(array $payload)
    {
        try {
            $session = $payload['data']['object'];

            Log::info('Webhook received: checkout.session.expired', [
                'session_id' => $session['id'],
                'payment_status' => $session['payment_status'],
                'mode' => $session['mode'],
                'metadata' => $session['metadata'] ?? []
            ]);

            return $this->handleFailedPayment($session);

        } catch (Exception $e) {
            Log::error('Webhook error in handleCheckoutSessionExpired: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'payload' => $payload
            ]);
            return $this->successMethod();
        }
    }


    /**
     * Mock exam purchase create/update
     */
    protected function handleMockExamPurchase(array $session)
    {
        try {
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

            // check existing purchase
            $existingPurchase = MockExamPurchase::where('user_id', $userId)
                ->where('mock_exam_id', $mockExamId)
                ->where('stripe_session_id', $session['id'])
                ->first();

            if ($existingPurchase) {
                Log::info('Mock exam purchase already exists', [
                    'purchase_id' => $existingPurchase->id
                ]);
                return $this->successMethod();
            }

            $paymentStatus = $session['payment_status'] ?? 'unpaid';

            $purchase = MockExamPurchase::create([
                'user_id' => $userId,
                'mock_exam_id' => $mockExamId,
                'stripe_session_id' => $session['id'],
                'transaction_id' => $session['payment_intent'] ?? null,
                'amount' => ($session['amount_total'] ?? 0) / 100,
                'currency' => $session['currency'] ?? 'gbp',
                'purchased_at' => now(),
                'total_marks' => $mockExam->total_marks,
                'status' => config('constants.mock_exam_purchase_status.NOT_STARTED'),
                'payment_status' => $paymentStatus === 'paid'
                    ? config('constants.stripe_payment_status.PAID')
                    : config('constants.stripe_payment_status.FAILED'),
                'purchased_by' => $session['metadata']['purchased_by'] ?? 'student',
            ]);

            Log::info('Mock exam purchase created', [
                'purchase_id' => $purchase->id,
                'status' => $purchase->payment_status
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
     * Mark failed payments
     */
    protected function handleFailedPayment(array $session)
    {
        try {
            $userId = $session['metadata']['user_id'] ?? null;
            $mockExamId = $session['metadata']['mock_exam_id'] ?? null;

            if ($userId && $mockExamId) {
                $purchase = MockExamPurchase::where('user_id', $userId)
                    ->where('mock_exam_id', $mockExamId)
                    ->where('stripe_session_id', $session['id'])
                    ->first();

                if ($purchase) {
                    $purchase->update([
                        'payment_status' => config('constants.stripe_payment_status.FAILED'),
                    ]);
                    Log::info('Purchase marked as FAILED', [
                        'purchase_id' => $purchase->id,
                        'session_id' => $session['id']
                    ]);
                }
            }

            return $this->successMethod();

        } catch (Exception $e) {
            Log::error('Failed payment handler error: ' . $e->getMessage());
            return $this->successMethod();
        }
    }
}
