<?php

namespace App\Services;

use App\Models\CoursePrice;
use App\Models\ManageStudentRecord;
use App\Models\StudentDetail;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Subscription;
use Stripe\StripeClient;

class SubscriptionService
{
    public function getSubscriptionsForParent($parentId)
    {
        try {
            // 1. Get all students linked to this parent
            $students = StudentDetail::where('parent_id', $parentId)
                ->with('student:id,first_name,last_name')
                ->get();

            $studentMap = $students->keyBy('child_id');

            // 2. Fetch active subscriptions for this parent (subscribed courses from Stripe)
            $subscriptions = Subscription::where('user_id', $parentId)
                ->where('stripe_status', config('constants.active_status', 'active'))
                ->with(['items'])
                ->orderBy('created_at', 'desc')
                ->get();

            // 3. Group by Student
            $groupedPurchases = [];

            foreach ($subscriptions as $subscription) {
                // Iterate over every subscription item so all subscribed courses are displayed
                foreach ($subscription->items as $item) {
                    $stripePriceId = $item->stripe_price ?? null;

                    if (!$stripePriceId) {
                        continue;
                    }

                    $coursePrice = CoursePrice::where('stripe_price_id', $stripePriceId)
                        ->with(['course', 'billingPeriod'])
                        ->first();

                    if (!$coursePrice || !$coursePrice->course) {
                        continue;
                    }

                    $course = $coursePrice->course;
                    $courseId = $course->id;

                    $assignment = ManageStudentRecord::where('course_id', $courseId)
                        ->where('buyer_id', '!=', $parentId)
                        ->whereIn('buyer_id', $studentMap->keys())
                        ->first();

                    $studentId = null;
                    $studentName = 'Unassigned';

                    if ($assignment) {
                        $studentId = $assignment->buyer_id;
                        if (isset($studentMap[$studentId])) {
                            $student = $studentMap[$studentId]->student;
                            $studentName = $student->first_name . ' ' . $student->last_name;
                        }
                    }

                    $key = $studentId ?? 'unassigned';

                    if (!isset($groupedPurchases[$key])) {
                        $groupedPurchases[$key] = [
                            'student_id' => $studentId,
                            'student_name' => $studentName,
                            'subscriptions' => []
                        ];
                    }

                    $invoice = null;
                    try {
                        $invoice = $subscription->latestInvoice();
                    } catch (\Exception $e) {
                        Log::warning("Failed to fetch latest invoice for subscription {$subscription->id}: " . $e->getMessage());
                    }

                    $amountDue = 0.00;
                    $paidAmount = 0.00;
                    $balance = 0.00;
                    $status = 'NOT_DUE';
                    $datePaid = $subscription->created_at->format('d-M-Y');
                    $receiptUrl = null;

                    if ($invoice) {
                        $amountDue = $invoice->amount_due / 100;
                        $paidAmount = $invoice->amount_paid / 100;
                        $balance = $invoice->amount_remaining / 100;
                        $datePaid = \Carbon\Carbon::createFromTimestamp($invoice->created)->format('d-M-Y');
                        $receiptUrl = $invoice->hosted_invoice_url;

                        if ($invoice->status === 'paid') {
                            $status = 'PAID';
                        } elseif ($invoice->status === 'open') {
                            $status = 'PAY';
                        } elseif ($invoice->status === 'void') {
                            $status = 'VOID';
                        } elseif ($invoice->status === 'uncollectible') {
                            $status = 'FAILED';
                        }
                    } else {
                        if ($subscription->stripe_status === 'active') {
                            $status = 'PAID';
                            $amountDue = (float) $coursePrice->amount;
                            $paidAmount = $amountDue;
                        } else {
                            $status = 'PAY';
                            $amountDue = (float) $coursePrice->amount;
                            $balance = $amountDue;
                        }
                    }

                    $academicYear = 'N/A';
                    if ($course->acdemicyears->isNotEmpty()) {
                        $year = $course->acdemicyears->first();
                        $academicYear = $year->start_year . '/' . $year->end_year;
                    }

                    $termName = $coursePrice->billingPeriod->name ?? 'Subscription';

                    $groupedPurchases[$key]['subscriptions'][] = [
                        'course_id' => $courseId,
                        'course_name' => $course->name ?? 'Unknown Course',
                        'academic_year' => $academicYear,
                        'payments' => [
                            [
                                'term_name' => $termName,
                                'amount_due' => $amountDue,
                                'due_date' => $datePaid,
                                'paid_amount' => $paidAmount,
                                'balance' => $balance,
                                'status' => $status,
                                'date_paid' => $datePaid,
                                'receipt_url' => $receiptUrl,
                                'receipt_pdf_url' => $invoice ? $invoice->invoice_pdf : null
                            ]
                        ]
                    ];
                }
            }

            return array_values($groupedPurchases);

        } catch (\Exception $e) {
            Log::error("Error fetching parent subscriptions: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get receipt URL from Stripe Session ID
     */
    private function getReceiptUrl($sessionId)
    {
        try {
            $stripe = new StripeClient(config('services.stripe.secret'));
            
            $session = $stripe->checkout->sessions->retrieve($sessionId);
            
            if (!$session->payment_intent) {
                return null;
            }

            $paymentIntent = $stripe->paymentIntents->retrieve($session->payment_intent);
            
            if (!$paymentIntent->latest_charge) {
                return null;
            }

            $charge = $stripe->charges->retrieve($paymentIntent->latest_charge);
            
            return $charge->receipt_url;

        } catch (\Exception $e) {
            Log::warning("Failed to fetch receipt URL for session {$sessionId}: " . $e->getMessage());
            return null;
        }
    }
}

