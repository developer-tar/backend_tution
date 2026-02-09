<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Subscription;
use Stripe\Stripe;
use Stripe\Subscription as StripeSubscription;

/**
 * Creates subscription and subscription_items in DB from a Stripe checkout session (subscription mode).
 * Used when user completes subscription payment - either from webhook or from payment-success page (fulfill).
 */
class SubscriptionFulfillmentService
{
    /**
     * Ensure subscription is saved to DB from checkout session (idempotent).
     * Session array must have: metadata.user_id, customer, subscription (Stripe subscription id).
     *
     * @param array $session Checkout session as array (from webhook or Stripe retrieve)
     * @return bool True if subscription was created or already existed
     */
    public function fulfillFromSession(array $session): bool
    {
        $userId = $session['metadata']['user_id'] ?? null;
        $customerId = $session['customer'] ?? null;
        $subscriptionId = $session['subscription'] ?? null;

        if (!$subscriptionId || !$userId) {
            Log::warning('Subscription fulfillment: missing subscription_id or user_id', [
                'session_id' => $session['id'] ?? null,
            ]);
            return false;
        }

        $user = User::find($userId);
        if (!$user) {
            Log::error('Subscription fulfillment: user not found', ['user_id' => $userId]);
            return false;
        }

        if ($customerId && !$user->stripe_id) {
            $user->stripe_id = $customerId;
            $user->save();
            Log::info('Subscription fulfillment: set user stripe_id', ['user_id' => $userId]);
        }

        $existingSubscription = Subscription::where('stripe_id', $subscriptionId)->where('user_id', $userId)->with('items')->first();
        if ($existingSubscription) {
            Log::info('Subscription fulfillment: already exists in DB', ['subscription_id' => $subscriptionId]);
            $this->clearCartForSubscription($userId, $existingSubscription->items->pluck('stripe_price')->filter()->toArray());
            return true;
        }

        try {
            Stripe::setApiKey(config('constants.stripe_secret'));
            $stripeSubscription = StripeSubscription::retrieve($subscriptionId, [
                'expand' => ['items.data.price'],
            ]);
            $data = json_decode(json_encode($stripeSubscription), true);
        } catch (\Exception $e) {
            Log::error('Subscription fulfillment: failed to retrieve from Stripe', [
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }

        $firstItem = $data['items']['data'][0] ?? null;
        $isSinglePrice = count($data['items']['data']) === 1;
        $subscriptionType = $data['metadata']['type'] ?? $data['metadata']['name'] ?? 'default';
        $trialEndsAt = isset($data['trial_end']) ? \Carbon\Carbon::createFromTimestamp($data['trial_end']) : null;

        $subscription = $user->subscriptions()->create([
            'type' => $subscriptionType,
            'stripe_id' => $data['id'],
            'stripe_status' => $data['status'],
            'stripe_price' => $isSinglePrice && $firstItem ? ($firstItem['price']['id'] ?? null) : null,
            'quantity' => $isSinglePrice && $firstItem && isset($firstItem['quantity']) ? $firstItem['quantity'] : null,
            'trial_ends_at' => $trialEndsAt,
            'ends_at' => null,
        ]);

        foreach ($data['items']['data'] as $item) {
            $subscription->items()->create([
                'stripe_id' => $item['id'],
                'stripe_product' => $item['price']['product'],
                'stripe_price' => $item['price']['id'],
                'quantity' => $item['quantity'] ?? null,
            ]);
        }

        Log::info('Subscription fulfillment: saved to DB', [
            'subscription_id' => $subscription->id,
            'stripe_id' => $data['id'],
            'user_id' => $userId,
        ]);

        $priceIds = collect($data['items']['data'])->pluck('price.id')->filter()->toArray();
        $this->clearCartForSubscription($userId, $priceIds);

        return true;
    }

    /**
     * Clear cart items for the user that match the subscription's price IDs.
     */
    protected function clearCartForSubscription(int $userId, array $priceIds): void
    {
        if (empty($priceIds)) {
            return;
        }

        try {
            $deletedCount = Cart::where('user_id', $userId)->whereIn('price_id', $priceIds)->delete();
            Log::info('Subscription fulfillment: cart cleared', [
                'user_id' => $userId,
                'price_ids' => $priceIds,
                'deleted_count' => $deletedCount,
            ]);
        } catch (\Exception $e) {
            Log::warning('Subscription fulfillment: cart clear failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
