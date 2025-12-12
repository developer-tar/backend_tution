<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\PaperPurchaseListRequest;
use App\Models\PaperPurchase;
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

            // Group by parent (user_id) and aggregate
            $groupedPurchases = $allPurchases->groupBy('user_id')->map(function ($purchases, $userId) {
                $parent = $purchases->first()->user;
                $totalPapers = $purchases->count();
                $totalAmount = $purchases->sum('amount');
                $currency = $purchases->first()->currency ?? 'gbp';
                
                // Payment status summary
                $paymentStatusSummary = $purchases->groupBy('payment_status')->map->count();
                
                // Get latest purchase date
                $latestPurchaseDate = $purchases->max('purchased_at');

                // Get individual purchases for this parent
                $purchaseDetails = $purchases->map(function ($purchase) {
                    return [
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
                    ];
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

