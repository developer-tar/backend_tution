<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CoursePrice;
use App\Models\ManageStudentRecord;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Cashier;
use Stripe\Stripe;
use Stripe\Price;
use Stripe\Product;

class CoursePurchaseController extends Controller
{
    /**
     * Parent checkout for course (similar to paper and mock exam checkout)
     */
    public function parentCheckout(Request $request)
    {
        $request->validate([
            'course_id' => 'required|exists:courses,id',
            'price_id' => 'required|string', // Stripe price ID
            'student_id' => 'nullable|exists:users,id', // Optional: for which student
        ]);

        try {
            $user = Auth::user(); // Parent user
            $course = Course::findOrFail($request->course_id);

            // Validate that the price_id belongs to this course
            $coursePrice = CoursePrice::where('course_id', $course->id)
                ->where('stripe_price_id', $request->price_id)
                ->first();

            // If price not found, check if price_id is invalid (not a Stripe price ID format)
            // and try to find/create the correct Stripe price
            if (!$coursePrice) {
                // Check if the provided price_id is invalid (numeric or doesn't start with 'price_')
                $isInvalidPriceId = is_numeric($request->price_id) ||
                    (is_string($request->price_id) && strpos($request->price_id, 'price_') !== 0);

                if ($isInvalidPriceId) {
                    // Try to find course price by ID if price_id is numeric (database ID)
                    if (is_numeric($request->price_id)) {
                        $coursePrice = CoursePrice::where('course_id', $course->id)
                            ->where('id', $request->price_id)
                            ->with(['billingPeriod', 'mode'])
                            ->first();
                    }

                    // If still not found, try to find any price for this course
                    if (!$coursePrice) {
                        $coursePrice = CoursePrice::where('course_id', $course->id)
                            ->with(['billingPeriod', 'mode'])
                            ->first();
                    }

                    // If we found a course price but it has invalid or missing Stripe price ID, create one
                    if ($coursePrice && (!$coursePrice->stripe_price_id ||
                        !is_string($coursePrice->stripe_price_id) ||
                        strpos($coursePrice->stripe_price_id, 'price_') !== 0)) {
                        // Create Stripe price on the fly
                        try {
                            $stripeSecret = config('constants.stripe_secret');
                            if (!$stripeSecret) {
                                Log::error("Stripe API key not configured for course price creation");
                                return sendError('Payment system is not configured. Please contact support.', [], 500);
                            }

                            Stripe::setApiKey($stripeSecret);

                            // Get or create Stripe product
                            if (!$coursePrice->stripe_product_id) {
                                $stripeProduct = Product::create([
                                    'name' => $course->name,
                                    'description' => $course->description ?? '',
                                ]);
                                $coursePrice->update(['stripe_product_id' => $stripeProduct->id]);
                            }

                            // Create Stripe price (recurring subscription for courses)
                            $stripePriceData = [
                                'currency' => strtolower($coursePrice->currency ?? 'gbp'),
                                'unit_amount' => intval($coursePrice->amount * 100),
                                'product' => $coursePrice->stripe_product_id,
                            ];

                            // Add recurring if billing period exists
                            if ($coursePrice->billingPeriod) {
                                $stripePriceData['recurring'] = [
                                    'interval' => 'month',
                                    'interval_count' => $coursePrice->billingPeriod->period ?? 1,
                                ];
                            }

                            $stripePrice = Price::create($stripePriceData);
                            $coursePrice->update(['stripe_price_id' => $stripePrice->id]);

                            Log::info("Created Stripe price for course on-the-fly", [
                                'course_id' => $course->id,
                                'course_price_id' => $coursePrice->id,
                                'stripe_price_id' => $stripePrice->id
                            ]);

                            // Update the price_id to use the newly created Stripe price ID
                            $request->merge(['price_id' => $stripePrice->id]);
                        } catch (\Exception $e) {
                            Log::error("Failed to create Stripe price for course: {$e->getMessage()}", [
                                'course_id' => $course->id,
                                'course_price_id' => $coursePrice->id ?? null
                            ]);
                            return sendError('Failed to process payment configuration. Please contact support.', [], 500);
                        }
                    } else {
                        return sendError('Invalid price for this course', [], 400);
                    }
                } else {
                    return sendError('Invalid price for this course', [], 400);
                }
            }

            // Check if already purchased for this student/parent
            $existingPurchase = ManageStudentRecord::where('buyer_id', $user->id)
                ->where('course_id', $course->id)
                ->where('model_type', Course::class)
                ->when($request->student_id, fn($q) => $q->where('user_id', $request->student_id))
                ->first();

            if ($existingPurchase) {
                return sendError('This course has already been purchased', [], 400);
            }

            if (!$request->price_id) {
                return sendError('This course is not available for purchase', [], 400);
            }

            $frontendUrl = config('app.frontend_url', 'http://localhost:3000');

            // Create one-time checkout session using Cashier (same as paper/mock exam pattern)
            /** @var \Laravel\Cashier\Checkout $checkout */
            $checkout = $user->checkout($request->price_id, [
                'success_url' => $frontendUrl . '/parent/course/payment-success?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $frontendUrl . '/parent/course/payment-cancel',
                'payment_method_types' => ['card'],
                'metadata' => [
                    'course_id' => $course->id,
                    'price_id' => $request->price_id,
                    'user_id' => $user->id, // Parent ID
                    'student_id' => $request->student_id ?? null, // Student ID (optional)
                    'type' => 'course_purchase',
                    'purchased_by' => 'parent',
                ],
            ]);

            return response()->json([
                'success' => true,
                'checkout_url' => $checkout->url,
                'session_id' => $checkout->id,
                'message' => 'Redirecting to payment...',
            ]);
        } catch (Exception $e) {
            Log::error("Failed to create parent course checkout: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
            return sendError('An error occurred while processing your request', [], 500);
        }
    }

    /**
     * Purchase course (one-time payment) - For students
     */
    public function purchaseCourse(Request $request)
    {
        $request->validate([
            'course_id' => 'required|exists:courses,id',
            'price_id' => 'required|string', // Stripe price ID
        ]);

        try {
            $user = Auth::user();
            $course = Course::findOrFail($request->course_id);

            // Validate that the price_id belongs to this course
            $coursePrice = CoursePrice::where('course_id', $course->id)
                ->where('stripe_price_id', $request->price_id)
                ->first();

            // If price not found, check if price_id is invalid (not a Stripe price ID format)
            // and try to find/create the correct Stripe price
            if (!$coursePrice) {
                // Check if the provided price_id is invalid (numeric or doesn't start with 'price_')
                $isInvalidPriceId = is_numeric($request->price_id) ||
                    (is_string($request->price_id) && strpos($request->price_id, 'price_') !== 0);

                if ($isInvalidPriceId) {
                    // Try to find course price by ID if price_id is numeric (database ID)
                    if (is_numeric($request->price_id)) {
                        $coursePrice = CoursePrice::where('course_id', $course->id)
                            ->where('id', $request->price_id)
                            ->with(['billingPeriod', 'mode'])
                            ->first();
                    }

                    // If still not found, try to find any price for this course
                    if (!$coursePrice) {
                        $coursePrice = CoursePrice::where('course_id', $course->id)
                            ->with(['billingPeriod', 'mode'])
                            ->first();
                    }

                    // If we found a course price but it has invalid or missing Stripe price ID, create one
                    if ($coursePrice && (!$coursePrice->stripe_price_id ||
                        !is_string($coursePrice->stripe_price_id) ||
                        strpos($coursePrice->stripe_price_id, 'price_') !== 0)) {
                        // Create Stripe price on the fly
                        try {
                            $stripeSecret = config('constants.stripe_secret');
                            if (!$stripeSecret) {
                                Log::error("Stripe API key not configured for course price creation");
                                return sendError('Payment system is not configured. Please contact support.', [], 500);
                            }

                            Stripe::setApiKey($stripeSecret);

                            // Get or create Stripe product
                            if (!$coursePrice->stripe_product_id) {
                                $stripeProduct = Product::create([
                                    'name' => $course->name,
                                    'description' => $course->description ?? '',
                                ]);
                                $coursePrice->update(['stripe_product_id' => $stripeProduct->id]);
                            }

                            // Create Stripe price (recurring subscription for courses)
                            $stripePriceData = [
                                'currency' => strtolower($coursePrice->currency ?? 'gbp'),
                                'unit_amount' => intval($coursePrice->amount * 100),
                                'product' => $coursePrice->stripe_product_id,
                            ];

                            // Add recurring if billing period exists
                            if ($coursePrice->billingPeriod) {
                                $stripePriceData['recurring'] = [
                                    'interval' => 'month',
                                    'interval_count' => $coursePrice->billingPeriod->period ?? 1,
                                ];
                            }

                            $stripePrice = Price::create($stripePriceData);
                            $coursePrice->update(['stripe_price_id' => $stripePrice->id]);

                            Log::info("Created Stripe price for course on-the-fly (student purchase)", [
                                'course_id' => $course->id,
                                'course_price_id' => $coursePrice->id,
                                'stripe_price_id' => $stripePrice->id
                            ]);

                            // Update the price_id to use the newly created Stripe price ID
                            $request->merge(['price_id' => $stripePrice->id]);
                        } catch (\Exception $e) {
                            Log::error("Failed to create Stripe price for course: {$e->getMessage()}", [
                                'course_id' => $course->id,
                                'course_price_id' => $coursePrice->id ?? null
                            ]);
                            return sendError('Failed to process payment configuration. Please contact support.', [], 500);
                        }
                    } else {
                        return sendError('Invalid price for this course', [], 400);
                    }
                } else {
                    return sendError('Invalid price for this course', [], 400);
                }
            }

            // Check if already purchased
            $existingPurchase = ManageStudentRecord::where('buyer_id', $user->id)
                ->where('course_id', $course->id)
                ->where('model_type', Course::class)
                ->first();

            if ($existingPurchase) {
                return sendError('You have already purchased this course', [], 400);
            }

            if (!$request->price_id) {
                return sendError('This course is not available for purchase', [], 400);
            }

            $frontendUrl = config('app.frontend_url', 'http://localhost:3000');

            // Create one-time checkout session using Cashier
            /** @var \Laravel\Cashier\Checkout $checkout */
            $checkout = $user->checkout($request->price_id, [
                'success_url' => $frontendUrl . '/course/payment-success?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $frontendUrl . '/course/payment-cancel',
                'payment_method_types' => ['card'],
                'metadata' => [
                    'course_id' => $course->id,
                    'price_id' => $request->price_id,
                    'user_id' => $user->id, // Student ID
                    'student_id' => null, // Student buying for themselves
                    'type' => 'course_purchase',
                    'purchased_by' => 'student',
                ],
            ]);

            return sendResponse([
                'checkout_url' => $checkout->url,
                'session_id' => $checkout->id,
            ], 'Redirecting to payment...');
        } catch (Exception $e) {
            return errorLog("Failed to create course checkout: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }
}
