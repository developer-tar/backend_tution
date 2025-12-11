<?php

namespace App\Http\Controllers\Api;

use App\Models\Cart;
use App\Models\MockExam;
use App\Models\MockExamPurchase;
use App\Models\Paper;
use App\Models\PaperPurchase;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Http\Controllers\WebhookController;
use Stripe\Stripe;
use Stripe\Checkout\Session as StripeSession;

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
                // Check metadata type to route to appropriate handler
                $type = $session['metadata']['type'] ?? null;
                
                try {
                    if ($type === 'paper_purchase') {
                        $result = $this->handlePaperPurchase($session);
                    } else {
                        $result = $this->handleMockExamPurchase($session);
                    }
                    
                    // Clear cart items after successful purchase
                    try {
                        $this->clearCartAfterPurchase($session);
                    } catch (Exception $cartException) {
                        // Log cart clearing error but don't fail the webhook
                        Log::error('Cart clearing failed after purchase', [
                            'error' => $cartException->getMessage(),
                            'file' => $cartException->getFile(),
                            'line' => $cartException->getLine(),
                            'session_id' => $session['id'] ?? null
                        ]);
                    }
                    
                    return $result;
                } catch (Exception $e) {
                    Log::error('Payment processing failed', [
                        'error' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'trace' => $e->getTraceAsString(),
                        'session_id' => $session['id'] ?? null,
                        'type' => $type
                    ]);
                    // Still return success to prevent webhook retries
                    return $this->successMethod();
                }
            }

            if ($session['mode'] === 'subscription') {
                $result = $this->handleSubscriptionPurchase($session);
                // Clear cart items after successful subscription
                $this->clearCartAfterPurchase($session);
                return $result;
            }

            Log::warning('Unknown session mode', ['mode' => $session['mode']]);
            return $this->successMethod();

        } catch (Exception $e) {
            Log::error('Webhook error in handleCheckoutSessionCompleted: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'session_id' => $payload['data']['object']['id'] ?? null,
                'metadata' => $payload['data']['object']['metadata'] ?? [],
                'payment_status' => $payload['data']['object']['payment_status'] ?? null
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

            // Cart clearing is handled in handleCheckoutSessionCompleted after purchase creation
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
     * Paper purchase create/update
     */
    protected function handlePaperPurchase(array $session)
    {
        try {
            $paperId = $session['metadata']['paper_id'] ?? null;
            $userId = $session['metadata']['user_id'] ?? null;

            Log::info('Paper webhook: Processing purchase', [
                'session_id' => $session['id'] ?? null,
                'paper_id' => $paperId,
                'user_id' => $userId,
                'metadata' => $session['metadata'] ?? []
            ]);

            if (!$paperId || !$userId) {
                Log::error('Paper webhook: Missing metadata', [
                    'metadata' => $session['metadata'] ?? [],
                    'session_id' => $session['id'] ?? null
                ]);
                return $this->successMethod();
            }

            $user = User::find($userId);
            $paper = Paper::find($paperId);

            if (!$user || !$paper) {
                Log::error('Paper webhook: User or Paper not found', [
                    'user_id' => $userId,
                    'paper_id' => $paperId,
                    'user_exists' => $user !== null,
                    'paper_exists' => $paper !== null,
                    'session_id' => $session['id'] ?? null
                ]);
                return $this->successMethod();
            }

            // check existing purchase
            $existingPurchase = PaperPurchase::where('user_id', $userId)
                ->where('paper_id', $paperId)
                ->where('stripe_session_id', $session['id'])
                ->first();

            if ($existingPurchase) {
                // Update payment status if purchase already exists
                $existingPurchase->update([
                    'payment_status' => config('constants.stripe_payment_status.PAID'),
                ]);
                Log::info('Paper purchase updated', [
                    'purchase_id' => $existingPurchase->id
                ]);
                return $this->successMethod();
            }

            $paymentStatus = $session['payment_status'] ?? 'unpaid';
            
            // Handle student_id: convert empty string to null (for foreign key constraint)
            $studentId = $session['metadata']['student_id'] ?? null;
            $studentId = ($studentId === '' || $studentId === null) ? null : (int)$studentId;

            // Validate constants exist
            $notStartedStatus = config('constants.mock_exam_purchase_status.NOT_STARTED');
            $paidStatus = config('constants.stripe_payment_status.PAID');
            $failedStatus = config('constants.stripe_payment_status.FAILED');
            
            if (!$notStartedStatus || !$paidStatus || !$failedStatus) {
                Log::error('Missing constants for paper purchase', [
                    'not_started' => $notStartedStatus,
                    'paid' => $paidStatus,
                    'failed' => $failedStatus
                ]);
                throw new Exception('Missing required constants for paper purchase');
            }

            Log::info('Creating paper purchase record', [
                'user_id' => (int)$userId,
                'paper_id' => (int)$paperId,
                'student_id' => $studentId,
                'amount' => ($session['amount_total'] ?? 0) / 100,
                'currency' => $session['currency'] ?? 'gbp',
                'payment_status' => $paymentStatus,
                'total_marks' => $paper->total_marks,
                'paper_name' => $paper->name
            ]);

            try {
                $purchaseData = [
                    'user_id' => (int)$userId,
                    'paper_id' => (int)$paperId,
                    'student_id' => $studentId,
                    'stripe_session_id' => $session['id'],
                    'transaction_id' => $session['payment_intent'] ?? null,
                    'amount' => ($session['amount_total'] ?? 0) / 100,
                    'currency' => $session['currency'] ?? 'gbp',
                    'purchased_at' => now(),
                    'total_marks' => $paper->total_marks, // Can be null
                    'status' => $notStartedStatus,
                    'payment_status' => $paymentStatus === 'paid' ? $paidStatus : $failedStatus,
                    'purchased_by' => $session['metadata']['purchased_by'] ?? 'student',
                ];
                
                Log::info('Paper purchase data prepared', $purchaseData);
                
                $purchase = PaperPurchase::create($purchaseData);
            } catch (\Illuminate\Database\QueryException $dbException) {
                Log::error('Database error creating paper purchase', [
                    'error' => $dbException->getMessage(),
                    'error_code' => $dbException->getCode(),
                    'sql_state' => $dbException->errorInfo[0] ?? null,
                    'sql_error' => $dbException->errorInfo[2] ?? null,
                    'user_id' => (int)$userId,
                    'paper_id' => (int)$paperId,
                    'student_id' => $studentId,
                    'purchase_data' => $purchaseData ?? []
                ]);
                throw $dbException; // Re-throw to be caught by outer catch
            } catch (\Exception $e) {
                Log::error('Unexpected error creating paper purchase', [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e; // Re-throw to be caught by outer catch
            }

            Log::info('Paper purchase created', [
                'purchase_id' => $purchase->id,
                'status' => $purchase->payment_status
            ]);

            // Cart clearing is handled in handleCheckoutSessionCompleted after purchase creation
            return $this->successMethod();

        } catch (Exception $e) {
            Log::error('Paper purchase error: ' . $e->getMessage(), [
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
            $type = $session['metadata']['type'] ?? null;
            
            // Handle mock exam purchase
            if ($type === 'mock_exam_purchase' || !$type) {
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
                        Log::info('Mock exam purchase marked as FAILED', [
                            'purchase_id' => $purchase->id,
                            'session_id' => $session['id']
                        ]);
                    }
                }
            }
            
            // Handle paper purchase
            if ($type === 'paper_purchase') {
                $paperId = $session['metadata']['paper_id'] ?? null;
                if ($userId && $paperId) {
                    $purchase = PaperPurchase::where('user_id', $userId)
                        ->where('paper_id', $paperId)
                        ->where('stripe_session_id', $session['id'])
                        ->first();

                    if ($purchase) {
                        $purchase->update([
                            'payment_status' => config('constants.stripe_payment_status.FAILED'),
                        ]);
                        Log::info('Paper purchase marked as FAILED', [
                            'purchase_id' => $purchase->id,
                            'session_id' => $session['id']
                        ]);
                    }
                }
            }

            return $this->successMethod();

        } catch (Exception $e) {
            Log::error('Failed payment handler error: ' . $e->getMessage());
            return $this->successMethod();
        }
    }

    /**
     * Handle subscription purchase
     */
    protected function handleSubscriptionPurchase(array $session)
    {
        try {
            // Subscription handling is typically done by Cashier automatically
            // This method is here for any additional logic if needed
            Log::info('Subscription purchase completed', [
                'session_id' => $session['id'],
                'subscription_id' => $session['subscription'] ?? null,
                'customer_id' => $session['customer'] ?? null
            ]);

            return $this->successMethod();

        } catch (Exception $e) {
            Log::error('Subscription purchase error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'session' => $session
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
                // Note: subscription_items might not exist yet, so we retry a few times
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
