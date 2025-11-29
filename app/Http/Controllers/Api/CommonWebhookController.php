<?php

namespace App\Http\Controllers\Api;

use App\Models\Cart;
use App\Models\MockExam;
use App\Models\MockExamPurchase;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierWebhookController;
use Stripe\Stripe;
use Stripe\Checkout\Session as StripeSession;

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

            // Only process if payment is successful
            if (($session['payment_status'] ?? '') !== 'paid') {
                Log::warning('Payment did not succeed', [
                    'session_id' => $session['id'],
                    'status' => $session['payment_status']
                ]);
                return $this->successMethod();
            }

            // Handle based on session mode
            if ($session['mode'] === 'payment') {
                $result = $this->handleOneTimePayment($session);
                // Clear cart items after successful purchase
                $this->clearCartAfterPurchase($session);
                return $result;
            }

            if ($session['mode'] === 'subscription') {
                // Subscription handling is typically done by Cashier automatically
                Log::info('Subscription purchase completed', [
                    'session_id' => $session['id'],
                    'subscription_id' => $session['subscription'] ?? null
                ]);
                // Clear cart items after successful subscription
                $this->clearCartAfterPurchase($session);
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

    /**
     * Extract price IDs from checkout session
     */
    protected function extractPriceIdsFromSession(array $session): array
    {
        $priceIds = [];

        try {
            if ($session['mode'] === 'payment') {
                // For one-time payments, extract from line_items
                if (isset($session['line_items']) && is_array($session['line_items'])) {
                    // If line_items is already expanded
                    if (isset($session['line_items']['data'])) {
                        foreach ($session['line_items']['data'] as $item) {
                            if (isset($item['price']['id'])) {
                                $priceIds[] = $item['price']['id'];
                            }
                        }
                    }
                } else {
                    // If line_items is not expanded, retrieve from Stripe API
                    try {
                        Stripe::setApiKey(config('constants.stripe_secret'));
                        $stripeSession = StripeSession::retrieve($session['id'], [
                            'expand' => ['line_items.data.price']
                        ]);

                        if (isset($stripeSession->line_items->data)) {
                            foreach ($stripeSession->line_items->data as $item) {
                                if (isset($item->price->id)) {
                                    $priceIds[] = $item->price->id;
                                }
                            }
                        }
                    } catch (Exception $e) {
                        Log::warning('Failed to retrieve line items from Stripe API', [
                            'session_id' => $session['id'],
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            } elseif ($session['mode'] === 'subscription') {
                // For subscriptions, try to extract from line_items first (most reliable)
                if (isset($session['line_items']) && is_array($session['line_items'])) {
                    if (isset($session['line_items']['data'])) {
                        foreach ($session['line_items']['data'] as $item) {
                            if (isset($item['price']['id'])) {
                                $priceIds[] = $item['price']['id'];
                            }
                        }
                    }
                } else {
                    // If line_items not expanded, try to retrieve from Stripe API
                    try {
                        Stripe::setApiKey(config('constants.stripe_secret'));
                        $stripeSession = StripeSession::retrieve($session['id'], [
                            'expand' => ['line_items.data.price']
                        ]);

                        if (isset($stripeSession->line_items->data)) {
                            foreach ($stripeSession->line_items->data as $item) {
                                if (isset($item->price->id)) {
                                    $priceIds[] = $item->price->id;
                                }
                            }
                        }
                    } catch (Exception $e) {
                        Log::warning('Failed to retrieve line items from Stripe API for subscription', [
                            'session_id' => $session['id'],
                            'error' => $e->getMessage()
                        ]);
                    }
                }

                // Fallback: If line_items didn't work, try subscription_items table
                if (empty($priceIds)) {
                    $subscriptionId = $session['subscription'] ?? null;
                    if ($subscriptionId) {
                        // Find subscription in database
                        $subscription = DB::table('subscriptions')
                            ->where('stripe_id', $subscriptionId)
                            ->first();

                        if ($subscription) {
                            // Get subscription items (they should exist by now, but check anyway)
                            $subscriptionItems = DB::table('subscription_items')
                                ->where('subscription_id', $subscription->id)
                                ->pluck('stripe_price')
                                ->toArray();

                            if (!empty($subscriptionItems)) {
                                $priceIds = array_merge($priceIds, $subscriptionItems);
                                Log::info('Retrieved price_ids from subscription_items', [
                                    'subscription_id' => $subscriptionId,
                                    'price_ids' => $priceIds
                                ]);
                            } else {
                                Log::warning('Subscription items not found in database yet', [
                                    'subscription_id' => $subscriptionId,
                                    'session_id' => $session['id'],
                                    'subscription_db_id' => $subscription->id,
                                    'note' => 'Subscription items may be created by a separate webhook. Cart may need manual clearing.'
                                ]);
                            }
                        } else {
                            Log::warning('Subscription not found in database, and line_items not available', [
                                'subscription_id' => $subscriptionId,
                                'session_id' => $session['id']
                            ]);
                        }
                    }
                }
            }
        } catch (Exception $e) {
            Log::error('Error extracting price IDs from session', [
                'session_id' => $session['id'] ?? null,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        }

        return array_unique($priceIds);
    }

    /**
     * Clear cart items after successful purchase
     */
    protected function clearCartAfterPurchase(array $session)
    {
        try {
            // Only clear if payment is successful
            if (($session['payment_status'] ?? '') !== 'paid') {
                Log::info('Skipping cart clear - payment not successful', [
                    'session_id' => $session['id'],
                    'payment_status' => $session['payment_status'] ?? 'unknown'
                ]);
                return;
            }

            // Extract price IDs from session
            $priceIds = $this->extractPriceIdsFromSession($session);

            if (empty($priceIds)) {
                Log::warning('No price IDs extracted from session, cannot clear cart', [
                    'session_id' => $session['id'],
                    'mode' => $session['mode'] ?? 'unknown'
                ]);
                return;
            }

            // Get user_id from metadata, subscription record, or customer
            $userId = $session['metadata']['user_id'] ?? null;
            $sessionId = $session['id'] ?? null;

            // For subscriptions, get user_id from subscription record if not in metadata
            if (!$userId && $session['mode'] === 'subscription') {
                $subscriptionId = $session['subscription'] ?? null;
                if ($subscriptionId) {
                    $subscription = DB::table('subscriptions')
                        ->where('stripe_id', $subscriptionId)
                        ->first();
                    
                    if ($subscription) {
                        $userId = $subscription->user_id;
                        Log::info('Retrieved user_id from subscription record', [
                            'subscription_id' => $subscriptionId,
                            'user_id' => $userId
                        ]);
                    } else {
                        // Try to get from customer_id
                        $customerId = $session['customer'] ?? null;
                        if ($customerId) {
                            $user = User::where('stripe_id', $customerId)->first();
                            if ($user) {
                                $userId = $user->id;
                                Log::info('Retrieved user_id from customer_id', [
                                    'customer_id' => $customerId,
                                    'user_id' => $userId
                                ]);
                            }
                        }
                    }
                }
            }

            if (!$userId && !$sessionId) {
                Log::warning('Cannot clear cart - no user_id or session_id found', [
                    'session_id' => $session['id'] ?? null,
                    'mode' => $session['mode'] ?? 'unknown',
                    'metadata' => $session['metadata'] ?? []
                ]);
                return;
            }

            // Clear cart items
            $deletedCount = $this->clearCartItems($userId, $sessionId, $priceIds);

            Log::info('Cart items cleared after purchase', [
                'session_id' => $session['id'],
                'user_id' => $userId,
                'price_ids' => $priceIds,
                'deleted_count' => $deletedCount
            ]);

        } catch (Exception $e) {
            Log::error('Error clearing cart after purchase', [
                'session_id' => $session['id'] ?? null,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            // Don't fail the webhook if cart clearing fails
        }
    }

    /**
     * Clear cart items by price IDs
     */
    protected function clearCartItems($userId, $sessionId, array $priceIds): int
    {
        try {
            if (empty($priceIds)) {
                return 0;
            }

            $query = Cart::whereIn('price_id', $priceIds);

            if ($userId) {
                $query->where('user_id', $userId);
            } elseif ($sessionId) {
                $query->where('session_id', $sessionId);
            } else {
                Log::warning('Cannot clear cart - no user_id or session_id provided');
                return 0;
            }

            $deletedCount = $query->count();
            $query->delete();

            return $deletedCount;

        } catch (Exception $e) {
            Log::error('Error in clearCartItems', [
                'user_id' => $userId,
                'session_id' => $sessionId,
                'price_ids' => $priceIds,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return 0;
        }
    }
}
