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

            if (!$coursePrice) {
                return sendError('Invalid price for this course', [], 400);
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

            if (!$coursePrice) {
                return sendError('Invalid price for this course', [], 400);
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
