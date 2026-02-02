<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\MockExam;
use App\Models\MockExamPurchase;
use App\Models\Paper;
use App\Models\PaperPurchase;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session as StripeSession;
use Stripe\Stripe;

/**
 * Fulfills basket one-time payments: creates paper_purchases and mock_exam_purchases
 * from Stripe checkout session line items and clears the cart.
 * Used by both the Stripe webhook and the payment-success page (when webhook may not fire).
 */
class BasketOrderFulfillmentService
{
    /**
     * Fulfill basket order by session ID (e.g. when user lands on payment-success).
     * Retrieves session from Stripe, then runs fulfill. Idempotent.
     */
    public function fulfillBySessionId(string $sessionId): bool
    {
        try {
            Stripe::setApiKey(config('constants.stripe_secret'));
            $stripeSession = StripeSession::retrieve($sessionId, [
                'expand' => ['line_items.data.price'],
            ]);
            $session = json_decode(json_encode($stripeSession), true);
            return $this->fulfill($session);
        } catch (Exception $e) {
            Log::error('BasketOrderFulfillmentService::fulfillBySessionId failed', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return false;
        }
    }

    /**
     * Fulfill basket order from session array (from webhook or after retrieve).
     * Only runs when mode is 'payment', payment_status is 'paid', and metadata.type is not set (basket checkout).
     * Creates PaperPurchase/MockExamPurchase per line item and clears cart. Idempotent.
     */
    public function fulfill(array $session): bool
    {
        try {
            if (($session['payment_status'] ?? '') !== 'paid') {
                Log::info('BasketOrderFulfillmentService: Skipping - payment not paid', [
                    'session_id' => $session['id'] ?? null,
                ]);
                return false;
            }
            if (($session['mode'] ?? '') !== 'payment') {
                Log::info('BasketOrderFulfillmentService: Skipping - not one-time payment', [
                    'session_id' => $session['id'] ?? null,
                    'mode' => $session['mode'] ?? null,
                ]);
                return false;
            }
            $type = $session['metadata']['type'] ?? null;
            if ($type !== null && $type !== '') {
                Log::info('BasketOrderFulfillmentService: Skipping - single-item checkout (type set)', [
                    'session_id' => $session['id'] ?? null,
                    'type' => $type,
                ]);
                return true; // Not basket; webhook/single-item flow handles it
            }

            $userId = $session['metadata']['user_id'] ?? null;
            if (!$userId) {
                Log::error('BasketOrderFulfillmentService: Missing user_id in metadata', [
                    'session_id' => $session['id'] ?? null,
                ]);
                return false;
            }
            if (!User::find($userId)) {
                Log::error('BasketOrderFulfillmentService: User not found', ['user_id' => $userId]);
                return false;
            }

            $lineItems = $this->getLineItems($session);
            if (empty($lineItems)) {
                Log::warning('BasketOrderFulfillmentService: No line items', [
                    'session_id' => $session['id'] ?? null,
                ]);
                return true;
            }

            $this->createPurchases($session, $lineItems);
            $this->clearCart($session, $lineItems);

            Log::info('BasketOrderFulfillmentService: Fulfillment completed', [
                'session_id' => $session['id'] ?? null,
                'user_id' => $userId,
            ]);
            return true;
        } catch (Exception $e) {
            Log::error('BasketOrderFulfillmentService::fulfill failed', [
                'session_id' => $session['id'] ?? null,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    /**
     * Get line items from session (array). Expands from Stripe API if not present.
     */
    protected function getLineItems(array $session): array
    {
        if (isset($session['line_items']['data']) && is_array($session['line_items']['data'])) {
            return $session['line_items']['data'];
        }
        if (isset($session['line_items']) && is_array($session['line_items'])) {
            return $session['line_items'];
        }
        try {
            Stripe::setApiKey(config('constants.stripe_secret'));
            $stripeSession = StripeSession::retrieve($session['id'], [
                'expand' => ['line_items.data.price'],
            ]);
            if (isset($stripeSession->line_items->data)) {
                return array_map(function ($item) {
                    $priceId = isset($item->price) ? $item->price->id : null;
                    return [
                        'price' => ['id' => $priceId],
                        'price_id' => $priceId,
                        'quantity' => $item->quantity ?? 1,
                        'amount_subtotal' => $item->amount_subtotal ?? 0,
                    ];
                }, $stripeSession->line_items->data);
            }
        } catch (Exception $e) {
            Log::warning('BasketOrderFulfillmentService: Failed to retrieve line items', [
                'session_id' => $session['id'] ?? null,
                'error' => $e->getMessage(),
            ]);
        }
        return [];
    }

    /**
     * Create PaperPurchase and MockExamPurchase for each line item. Idempotent.
     */
    protected function createPurchases(array $session, array $lineItems): void
    {
        $userId = (int) ($session['metadata']['user_id'] ?? 0);
        $sessionId = $session['id'] ?? null;
        $paidStatus = config('constants.stripe_payment_status.PAID');
        $notStartedStatus = config('constants.mock_exam_purchase_status.NOT_STARTED');
        $purchasedBy = $session['metadata']['purchased_by'] ?? config('constants.roles.PARENT');
        $currency = $session['currency'] ?? 'gbp';

        foreach ($lineItems as $item) {
            $priceId = $item['price']['id'] ?? $item['price_id'] ?? null;
            $quantity = (int) ($item['quantity'] ?? 1);
            $amountSubtotal = isset($item['amount_subtotal']) ? ($item['amount_subtotal'] / 100) : (($session['amount_total'] ?? 0) / 100 / max(1, count($lineItems)));

            if (!$priceId) {
                continue;
            }

            $paper = Paper::where('stripe_price_id', $priceId)->first();
            if ($paper) {
                $existingCount = PaperPurchase::where('user_id', $userId)
                    ->where('paper_id', $paper->id)
                    ->where('stripe_session_id', $sessionId)
                    ->count();
                $toCreate = max(0, $quantity - $existingCount);
                $amountEach = $quantity > 0 ? ($amountSubtotal / $quantity) : 0;
                for ($q = 0; $q < $toCreate; $q++) {
                    PaperPurchase::create([
                        'user_id' => $userId,
                        'paper_id' => $paper->id,
                        'student_id' => null,
                        'stripe_session_id' => $sessionId,
                        'transaction_id' => $session['payment_intent'] ?? null,
                        'amount' => $amountEach,
                        'currency' => $currency,
                        'purchased_at' => now(),
                        'total_marks' => $paper->total_marks,
                        'status' => $notStartedStatus,
                        'payment_status' => $paidStatus,
                        'purchased_by' => $purchasedBy,
                    ]);
                }
                Log::info('BasketOrderFulfillmentService: Paper purchase(s) created', [
                    'paper_id' => $paper->id,
                    'user_id' => $userId,
                    'quantity' => $quantity,
                    'session_id' => $sessionId,
                ]);
                continue;
            }

            $mockExam = MockExam::where('stripe_price_id', $priceId)->first();
            if ($mockExam) {
                $existingCount = MockExamPurchase::where('user_id', $userId)
                    ->where('mock_exam_id', $mockExam->id)
                    ->where('stripe_session_id', $sessionId)
                    ->count();
                $toCreate = max(0, $quantity - $existingCount);
                $amountEach = $quantity > 0 ? ($amountSubtotal / $quantity) : 0;
                for ($q = 0; $q < $toCreate; $q++) {
                    MockExamPurchase::create([
                        'user_id' => $userId,
                        'mock_exam_id' => $mockExam->id,
                        'student_id' => null,
                        'stripe_session_id' => $sessionId,
                        'transaction_id' => $session['payment_intent'] ?? null,
                        'amount' => $amountEach,
                        'currency' => $currency,
                        'purchased_at' => now(),
                        'total_marks' => $mockExam->total_marks ?? 0,
                        'status' => $notStartedStatus,
                        'payment_status' => $paidStatus,
                        'purchased_by' => $purchasedBy,
                    ]);
                }
                Log::info('BasketOrderFulfillmentService: Mock exam purchase(s) created', [
                    'mock_exam_id' => $mockExam->id,
                    'user_id' => $userId,
                    'quantity' => $quantity,
                    'session_id' => $sessionId,
                ]);
            }
        }
    }

    /**
     * Clear cart items for this user and the given line items' price IDs.
     */
    protected function clearCart(array $session, array $lineItems): void
    {
        $userId = $session['metadata']['user_id'] ?? null;
        $priceIds = [];
        foreach ($lineItems as $item) {
            $priceId = $item['price']['id'] ?? $item['price_id'] ?? null;
            if ($priceId) {
                $priceIds[] = $priceId;
            }
        }
        $priceIds = array_unique($priceIds);
        if (empty($priceIds) || !$userId) {
            return;
        }
        try {
            $deletedCount = Cart::where('user_id', $userId)->whereIn('price_id', $priceIds)->delete();
            Log::info('BasketOrderFulfillmentService: Cart cleared', [
                'user_id' => $userId,
                'price_ids' => $priceIds,
                'deleted_count' => $deletedCount,
            ]);
        } catch (Exception $e) {
            Log::error('BasketOrderFulfillmentService: Cart clear failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
