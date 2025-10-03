<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MockExam;
use App\Models\MockExamPurchase;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TestWebhookController extends Controller
{
    /**
     * Test webhook payload for mock exam purchase
     */
    public function testMockExamWebhook(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'mock_exam_id' => 'required|exists:mock_exams,id',
            'student_id' => 'nullable|exists:users,id',
        ]);

        try {
            // Simulate Stripe webhook payload
            $testPayload = [
                'type' => 'checkout.session.completed',
                'data' => [
                    'object' => [
                        'id' => 'cs_test_' . uniqid(),
                        'mode' => 'payment', // One-time payment
                        'payment_status' => 'paid',
                        'amount_total' => 2500, // £25.00 in pence
                        'currency' => 'gbp',
                        'payment_intent' => 'pi_test_' . uniqid(),
                        'metadata' => [
                            'type' => 'mock_exam_purchase',
                            'mock_exam_id' => $request->mock_exam_id,
                            'user_id' => $request->user_id,
                            'student_id' => $request->student_id,
                            'purchased_by' => $request->student_id ? 'parent' : 'student',
                        ]
                    ]
                ]
            ];

            // Call the webhook handler
            $webhookController = new CommonWebhookController();
            $result = $webhookController->handleCheckoutSessionCompleted($testPayload);

            // Get the created purchase
            $purchase = MockExamPurchase::where('user_id', $request->user_id)
                ->where('mock_exam_id', $request->mock_exam_id)
                ->latest()
                ->first();

            return sendResponse([
                'webhook_result' => 'success',
                'test_payload' => $testPayload,
                'created_purchase' => $purchase ? [
                    'id' => $purchase->id,
                    'user_id' => $purchase->user_id,
                    'student_id' => $purchase->student_id,
                    'mock_exam_id' => $purchase->mock_exam_id,
                    'amount' => $purchase->amount,
                    'purchased_by' => $purchase->purchased_by,
                    'status' => $purchase->status,
                ] : null
            ], 'Webhook test completed successfully');

        } catch (\Exception $e) {
            return errorLog("Webhook test failed: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    /**
     * Test subscription webhook
     */
    public function testSubscriptionWebhook(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        try {
            // Simulate subscription webhook payload
            $testPayload = [
                'type' => 'checkout.session.completed',
                'data' => [
                    'object' => [
                        'id' => 'cs_test_' . uniqid(),
                        'mode' => 'subscription', // Subscription
                        'payment_status' => 'paid',
                        'subscription' => 'sub_test_' . uniqid(),
                        'customer' => 'cus_test_' . uniqid(),
                        'metadata' => [
                            'type' => 'course_subscription',
                            'user_id' => $request->user_id,
                        ]
                    ]
                ]
            ];

            // Call the webhook handler
            $webhookController = new CommonWebhookController();
            $result = $webhookController->handleCheckoutSessionCompleted($testPayload);

            return sendResponse([
                'webhook_result' => 'success',
                'test_payload' => $testPayload,
                'message' => 'Subscription webhook handled by Cashier automatically'
            ], 'Subscription webhook test completed successfully');

        } catch (\Exception $e) {
            return errorLog("Subscription webhook test failed: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }
}
