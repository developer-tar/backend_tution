<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Parent\PaperPurchaseListRequest;
use App\Models\Paper;
use App\Models\PaperPurchase;
use App\Models\PaperRequestActivityLog;
use App\Models\RequestedPaperToHome;
use App\Models\StudentDetail;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Cashier;

class PaperPurchaseController extends Controller
{
    /**
     * Parent checkout for paper (similar to course checkout)
     */
    public function parentCheckout(Request $request)
    {
        $request->validate([
            'paper_id' => 'required|exists:papers,id',
            'student_id' => 'nullable|exists:users,id', // Optional: for which student
        ]);

        try {
            $user = Auth::user(); // Parent user
            $paper = Paper::with('format:id,name')->findOrFail($request->paper_id);

            // Check if already purchased for this student/parent
            $existingPurchase = PaperPurchase::where('user_id', $user->id)
                ->where('paper_id', $paper->id)
                ->when($request->student_id, fn($q) => $q->where('student_id', $request->student_id))
                ->first();

            if ($existingPurchase) {
                return sendError('This paper has already been purchased', [], 400);
            }

            if (!$paper->stripe_price_id) {
                return sendError('This paper is not available for purchase', [], 400);
            }

            $frontendUrl = config('app.frontend_url', 'http://localhost:3000');

            // Create one-time checkout session using Cashier (same as course pattern)
            $checkout = $user->checkout($paper->stripe_price_id, [
                'success_url' => $frontendUrl . '/parent/paper/payment-success?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $frontendUrl . '/parent/paper/payment-cancel',
                'payment_method_types' => ['card'],
                'metadata' => [
                    'paper_id' => $paper->id,
                    'user_id' => $user->id, // Parent ID
                    'student_id' => $request->student_id ?? null, // Student ID (optional)
                    'type' => 'paper_purchase',
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
            Log::error("Failed to create parent paper checkout: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
            return sendError('An error occurred while processing your request', [], 500);
        }
    }

    /**
     * Purchase paper (one-time payment) - Original method for students
     */
    public function purchasePaper(Request $request)
    {
        $request->validate([
            'paper_id' => 'required|exists:papers,id',
        ]);

        try {
            $user = Auth::user();
            $paper = Paper::with('format:id,name')->findOrFail($request->paper_id);

            // Check if already purchased
            $existingPurchase = PaperPurchase::where('user_id', $user->id)
                ->where('paper_id', $paper->id)
                ->first();

            if ($existingPurchase) {
                return sendError('You have already purchased this paper', [], 400);
            }

            if (!$paper->stripe_price_id) {
                return sendError('This paper is not available for purchase', [], 400);
            }

            $frontendUrl = config('app.frontend_url', 'http://localhost:3000');

            // Create one-time checkout session using Cashier
            $checkout = $user->checkout($paper->stripe_price_id, [
                'success_url' => $frontendUrl . '/paper/payment-success?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $frontendUrl . '/paper/payment-cancel',
                'payment_method_types' => ['card'],
                'metadata' => [
                    'paper_id' => $paper->id,
                    'user_id' => $user->id, // Student ID
                    'student_id' => null, // Student buying for themselves
                    'type' => 'paper_purchase',
                    'purchased_by' => 'student',
                ],
            ]);
            return sendResponse([
                'checkout_url' => $checkout->url,
                'session_id' => $checkout->id,
            ], 'Redirecting to payment...');
            

        } catch (Exception $e) {
            return errorLog("Failed to create paper checkout: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    /**
     * Verify payment success
     */
    public function verifyPayment(Request $request)
    {
        $request->validate([
            'session_id' => 'required|string',
        ]);

        try {
            $stripe = Cashier::stripe();
            $session = $stripe->checkout->sessions->retrieve($request->session_id);

            if ($session->payment_status !== 'paid') {
                return sendError('Payment not completed', [], 400);
            }

            $paperId = $session->metadata->paper_id ?? null;
            $userId = $session->metadata->user_id ?? null;

            if (!$paperId || !$userId) {
                return sendError('Invalid session metadata', [], 400);
            }

            // Check if purchase record already exists
            $purchase = PaperPurchase::where('user_id', $userId)
                ->where('paper_id', $paperId)
                ->where('stripe_session_id', $session->id)
                ->first();

            if (!$purchase) {
                return sendError('Purchase record not found', [], 404);
            }
            return sendResponse([
                'purchase_id' => $purchase->id,
                'paper_id' => $paperId,
            ], 'Payment verified successfully.');
            
        } catch (Exception $e) {
            return errorLog("Failed to verify payment: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
           
        }
    }

    /**
     * Get user's purchased papers with full paper details
     * 
     * @param PaperPurchaseListRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function myPurchases(PaperPurchaseListRequest $request)
    {
        try {
            $user = Auth::user();
            $validated = $request->validated();
            $paymentStatus = $validated['status'] ?? null; // Optional filter by payment status

            $purchasesQuery = PaperPurchase::with([
                'paper:id,name,description,format_id,category_id,price,currency,duration_minutes,total_marks',
                'paper.format:id,name',
                'paper.category:id,name',
                'student:id,first_name,last_name,email', // For parent purchases
            ])
                ->where(function ($q) use ($user) {
                    $q->where('user_id', $user->id)
                        ->orWhere('student_id', $user->id); // Include papers parent assigned to this student
                });

            // Apply payment status filter if provided
            if ($paymentStatus) {
                $purchasesQuery->where('payment_status', $paymentStatus);
            }

            $purchases = $purchasesQuery
                ->orderBy('purchased_at', 'desc')
                ->get();

            // Get all paper IDs from purchases
            $paperIds = $purchases->pluck('paper_id')->unique()->toArray();

            // Fetch all requested papers to home for these papers and this user in one query (optimize N+1)
            $requestedPapers = RequestedPaperToHome::withTrashed()
                ->where('parent_id', $user->id)
                ->whereIn('paper_id', $paperIds)
                ->get()
                ->keyBy('paper_id'); // Key by paper_id for easy lookup

            // Fetch all activity logs for these papers and this user in one query (optimize N+1)
            $activityLogs = PaperRequestActivityLog::with(['billingInformation'])
                ->where('user_id', $user->id)
                ->whereIn('paper_id', $paperIds)
                ->whereIn('action', ['created', 'updated', 'restored']) // Only Request Home actions
                ->orderBy('created_at', 'desc')
                ->get()
                ->groupBy('paper_id'); // Group by paper_id for easy lookup

            $purchases = $purchases->map(function ($purchase) use ($activityLogs, $requestedPapers) {
                $paper = $purchase->paper;

                if (!$paper) {
                    return null;
                }

                // Get PDFs from media collection
                $pdfs = $paper->getMedia('paper_pdfs')->map(function ($media) {
                    return [
                        'id' => $media->id,
                        'name' => $media->name,
                        'file_name' => $media->file_name,
                        'url' => $media->getUrl(),
                        'size' => $media->size,
                        'order' => $media->getCustomProperty('order', 0),
                        'original_name' => $media->getCustomProperty('original_name', $media->file_name),
                        'mime_type' => $media->mime_type,
                        'created_at' => $media->created_at?->toDateTimeString(),
                    ];
                })->sortBy('order')->values();

                // Get activity logs for this paper
                $paperActivityLogs = $activityLogs->get($purchase->paper_id, collect())->map(function ($log) {
                    $billingInfo = $log->billingInformation;
                    return [
                        'id' => $log->id,
                        'action' => $log->action,
                        'description' => $log->description,
                        'billing_information' => $billingInfo ? [
                            'id' => $billingInfo->id,
                            'address_line1' => $billingInfo->address_line1,
                            'address_line2' => $billingInfo->address_line2,
                            'city' => $billingInfo->city,
                            'state' => $billingInfo->state,
                            'postal_code' => $billingInfo->postal_code,
                            'country' => $billingInfo->country,
                            'phone' => $billingInfo->phone,
                        ] : null,
                        'old_values' => $log->old_values,
                        'new_values' => $log->new_values,
                        'created_at' => $log->created_at?->toDateTimeString(),
                        'ip_address' => $log->ip_address,
                    ];
                })->values();

                // Get billing_information_id from requested_papers_to_home table
                $requestedPaper = $requestedPapers->get($purchase->paper_id);
                $billingInformationId = $requestedPaper ? $requestedPaper->billing_information_id : null;

                return [
                    'purchase_id' => $purchase->id,
                    'paper_id' => $purchase->paper_id,
                    'paper_name' => $paper->name ?? 'N/A',
                    'paper_description' => $paper->description ?? null,
                    'paper_format' => $paper->format?->name ?? null,
                    'paper_category' => $paper->category?->name ?? null,
                    'paper_price' => $paper->price ? (float) $paper->price : null,
                    'paper_currency' => $paper->currency ?? null,
                    'paper_image' => $paper->getFirstMediaUrl('paper_image') ?: config('constants.dummy_image'),
                    'paper_pdfs' => $pdfs,
                    'paper_duration_minutes' => $paper->duration_minutes ?? null,
                    'paper_total_marks' => $paper->total_marks ?? null,
                    'amount' => $purchase->amount ? (float) $purchase->amount : null,
                    'currency' => $purchase->currency ?? 'gbp',
                    'payment_status' => $purchase->payment_status ?? null,
                    'purchased_at' => $purchase->purchased_at?->toDateTimeString(),
                    'purchased_by' => $purchase->purchased_by ?? 'student',
                    'student_id' => $purchase->student_id,
                    'student_name' => $purchase->student?->full_name ?? null,
                    'student_email' => $purchase->student?->email ?? null,
                    'status' => $purchase->status ?? null,
                    'score' => $purchase->score ?? null,
                    'started_at' => $purchase->started_at?->toDateTimeString(),
                    'completed_at' => $purchase->completed_at?->toDateTimeString(),
                    'billing_information_id' => $billingInformationId, // Added billing_information_id
                    'request_home_activity_logs' => $paperActivityLogs, // Added activity logs
                ];
            })->filter()->values();

            return sendResponse($purchases, 'Purchased papers fetched successfully');

        } catch (Exception $e) {
            Log::error("Failed to fetch user purchases: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
            return errorLog("Failed to fetch user purchases: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    /**
     * Parent: assign a student to a paper purchase (or unassign).
     * Purchase must belong to the authenticated parent; student must be one of the parent's students.
     */
    public function assignStudent(Request $request, $purchaseId)
    {
        $request->validate([
            'student_id' => 'nullable|integer|exists:users,id',
        ]);

        try {
            $parent = Auth::user();
            $purchase = PaperPurchase::where('id', $purchaseId)->where('user_id', $parent->id)->first();

            if (!$purchase) {
                return sendError('Purchase not found or you do not have permission to update it.', [], 404);
            }

            $studentId = $request->input('student_id');

            if ($studentId !== null) {
                $isMyStudent = StudentDetail::where('parent_id', $parent->id)
                    ->where('child_id', $studentId)
                    ->exists();
                if (!$isMyStudent) {
                    return sendError('You can only assign one of your own students to this paper.', [], 403);
                }
            }

            $purchase->student_id = $studentId;
            $purchase->save();

            $purchase->load('student:id,first_name,last_name,email');
            $student = $purchase->student;
            $studentName = $student ? trim($student->first_name . ' ' . $student->last_name) : null;

            return sendResponse([
                'purchase_id' => $purchase->id,
                'student_id' => $purchase->student_id,
                'student_name' => $studentName,
                'student_email' => $student?->email ?? null,
            ], $studentId ? 'Student assigned successfully.' : 'Student unassigned successfully.');
        } catch (Exception $e) {
            Log::error("Failed to assign student to paper purchase: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
            return sendError('An error occurred while updating the assignment.', [], 500);
        }
    }
}







