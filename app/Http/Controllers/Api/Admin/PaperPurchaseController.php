<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\PaperPurchaseListRequest;
use App\Models\BillingInformation;
use App\Models\PaperPurchase;
use App\Models\PaperRequestActivityLog;
use App\Models\RequestedPaperToHome;
use Exception;
use Illuminate\Support\Facades\Log;

class PaperPurchaseController extends Controller
{
    /**
     * Display aggregated list of paper purchases grouped by parent
     * 
     * @param PaperPurchaseListRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(PaperPurchaseListRequest $request)
    {
        try {
            $validated = $request->validated();
            $perPage = $validated['per_page'] ?? 10;
            $search = $validated['search'] ?? null;
            $paymentStatus = $validated['payment_status'] ?? null;
            $sortBy = $validated['sort_by'] ?? 'latest_purchase_date'; // latest_purchase_date, total_amount, parent_name
            $sortOrder = $validated['sort_order'] ?? 'desc';
            $dateFrom = $validated['date_from'] ?? null;
            $dateTo = $validated['date_to'] ?? null;

            // Base query - get all purchases with relationships
            $purchasesQuery = PaperPurchase::with([
                'user:id,first_name,last_name,email',
                'student:id,first_name,last_name,email',
                'paper:id,name,format_id,category_id,price,currency',
                'paper.format:id,name',
                'paper.category:id,name',
            ]);

            // Apply filters
            if ($paymentStatus) {
                $purchasesQuery->where('payment_status', $paymentStatus);
            }

            if ($dateFrom) {
                $purchasesQuery->whereDate('purchased_at', '>=', $dateFrom);
            }

            if ($dateTo) {
                $purchasesQuery->whereDate('purchased_at', '<=', $dateTo);
            }

            // Get all purchases
            $allPurchases = $purchasesQuery->get();

            // Get all paper IDs from purchases
            $paperIds = $allPurchases->pluck('paper_id')->unique()->toArray();
            $userIds = $allPurchases->pluck('user_id')->unique()->toArray();

            // Fetch all requested papers to home for these papers and users in one query (optimize N+1)
            $requestedPapersCollection = RequestedPaperToHome::withTrashed()
                ->whereIn('parent_id', $userIds)
                ->whereIn('paper_id', $paperIds)
                ->with(['billingInformation'])
                ->orderBy('updated_at', 'desc')
                ->get();

            // Create a lookup map: [parent_id][paper_id] => latest requested paper
            $requestedPapers = [];
            foreach ($requestedPapersCollection as $requestedPaper) {
                $key = "{$requestedPaper->parent_id}_{$requestedPaper->paper_id}";
                // Keep only the latest one (already ordered by updated_at desc)
                if (!isset($requestedPapers[$key])) {
                    $requestedPapers[$key] = $requestedPaper;
                }
            }

            // Fetch all activity logs for these papers and users in one query (optimize N+1)
            $activityLogsCollection = PaperRequestActivityLog::with(['billingInformation'])
                ->whereIn('user_id', $userIds)
                ->whereIn('paper_id', $paperIds)
                ->whereIn('action', ['created', 'updated', 'restored']) // Only Request Home actions
                ->orderBy('created_at', 'desc')
                ->get();

            // Group activity logs by user_id and paper_id
            $activityLogs = [];
            foreach ($activityLogsCollection as $log) {
                $key = "{$log->user_id}_{$log->paper_id}";
                if (!isset($activityLogs[$key])) {
                    $activityLogs[$key] = [];
                }
                $activityLogs[$key][] = $log;
            }

            // Group by parent (user_id) and aggregate
            $groupedPurchases = $allPurchases->groupBy('user_id')->map(function ($purchases, $userId) use ($requestedPapers, $activityLogs) {
                $parent = $purchases->first()->user;
                $totalPapers = $purchases->count();
                $totalAmount = $purchases->sum('amount');
                $currency = $purchases->first()->currency ?? 'gbp';
                
                // Payment status summary
                $paymentStatusSummary = $purchases->groupBy('payment_status')->map->count();
                
                // Get latest purchase date
                $latestPurchaseDate = $purchases->max('purchased_at');

                // Get individual purchases for this parent
                $purchaseDetails = $purchases->map(function ($purchase) use ($userId, $requestedPapers, $activityLogs) {
                    $paperId = $purchase->paper_id;
                    $formatName = strtolower($purchase->paper->format?->name ?? '');
                    $isPhysical = in_array($formatName, ['physical', 'any']);

                    // Get latest requested paper for this parent and paper
                    $key = "{$userId}_{$paperId}";
                    $paperRequested = $requestedPapers[$key] ?? null;
                    
                    // Get latest billing information for this paper
                    $latestBillingInfo = null;
                    if ($paperRequested && $paperRequested->billingInformation) {
                        $billingInfo = $paperRequested->billingInformation;
                        $latestBillingInfo = [
                            'id' => $billingInfo->id,
                            'address_line1' => $billingInfo->address_line1,
                            'address_line2' => $billingInfo->address_line2,
                            'city' => $billingInfo->city,
                            'state' => $billingInfo->state,
                            'postal_code' => $billingInfo->postal_code,
                            'country' => $billingInfo->country,
                            'phone' => $billingInfo->phone,
                            'updated_at' => $paperRequested->updated_at?->toDateTimeString(),
                        ];
                    }

                    // Get activity logs for this paper and parent
                    $paperActivityLogs = collect($activityLogs[$key] ?? [])->map(function ($log) {
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

                    $purchaseData = [
                        'purchase_id' => $purchase->id,
                        'paper_id' => $purchase->paper_id,
                        'paper_name' => $purchase->paper->name ?? 'N/A',
                        'format_id' => $purchase->paper->format_id ?? null,
                        'format_name' => $purchase->paper->format?->name ?? null,
                        'amount' => $purchase->amount,
                        'currency' => $purchase->currency,
                        'payment_status' => $purchase->payment_status,
                        'purchased_at' => $purchase->purchased_at?->toDateTimeString(),
                        'student_id' => $purchase->student_id,
                        'student_name' => $purchase->student?->full_name,
                        'student_email' => $purchase->student?->email,
                        'purchased_by' => $purchase->purchased_by,
                        'request_home_activity_logs' => $paperActivityLogs, // Activity logs for this paper
                    ];

                    // Include billing information only for physical format papers
                    if ($isPhysical) {
                        $purchaseData['latest_billing_information'] = $latestBillingInfo; // Latest billing info for this paper
                    }

                    return $purchaseData;
                })->values();

                return [
                    'parent_id' => $userId,
                    'parent_name' => $parent->full_name ?? 'N/A',
                    'parent_email' => $parent->email ?? 'N/A',
                    'total_papers' => $totalPapers,
                    'total_amount' => (float) $totalAmount,
                    'currency' => $currency,
                    'payment_status_summary' => [
                        'paid' => $paymentStatusSummary->get(config('constants.stripe_payment_status.PAID'), 0),
                        'pending' => $paymentStatusSummary->get(config('constants.stripe_payment_status.PENDING'), 0),
                        'failed' => $paymentStatusSummary->get(config('constants.stripe_payment_status.FAILED'), 0),
                        'cancelled' => $paymentStatusSummary->get(config('constants.stripe_payment_status.CANCELLED'), 0),
                        'expired' => $paymentStatusSummary->get(config('constants.stripe_payment_status.EXPIRED'), 0),
                        'refunded' => $paymentStatusSummary->get(config('constants.stripe_payment_status.REFUNDED'), 0),
                    ],
                    'latest_purchase_date' => $latestPurchaseDate?->toDateTimeString(),
                    'purchases' => $purchaseDetails,
                ];
            })->values();

            // Apply search filter (parent name or email)
            if ($search) {
                $groupedPurchases = $groupedPurchases->filter(function ($item) use ($search) {
                    $searchLower = strtolower($search);
                    return str_contains(strtolower($item['parent_name']), $searchLower) ||
                           str_contains(strtolower($item['parent_email']), $searchLower);
                })->values();
            }

            // Apply sorting
            $sorted = $groupedPurchases->sortBy(function ($item) use ($sortBy) {
                switch ($sortBy) {
                    case 'parent_name':
                        return strtolower($item['parent_name']);
                    case 'total_amount':
                        return $item['total_amount'];
                    case 'total_papers':
                        return $item['total_papers'];
                    case 'latest_purchase_date':
                    default:
                        return $item['latest_purchase_date'] ?? '';
                }
            }, SORT_REGULAR);
            
            // Reverse if descending order
            $groupedPurchases = $sortOrder === 'desc' ? $sorted->reverse()->values() : $sorted->values();

            // Paginate manually
            $currentPage = $validated['page'] ?? 1;
            $items = $groupedPurchases->all();
            $total = count($items);
            $offset = ($currentPage - 1) * $perPage;
            $paginatedItems = array_slice($items, $offset, $perPage);

            return sendResponse([
                'data' => array_values($paginatedItems),
                'pagination' => [
                    'current_page' => (int) $currentPage,
                    'per_page' => (int) $perPage,
                    'total' => $total,
                    'last_page' => (int) ceil($total / $perPage),
                    'from' => $offset + 1,
                    'to' => min($offset + $perPage, $total),
                ],
            ], 'Paper purchases fetched successfully');

        } catch (Exception $e) {
            Log::error("Failed to fetch paper purchases: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
            return sendError('An error occurred while fetching paper purchases', [], 500);
        }
    }
}

