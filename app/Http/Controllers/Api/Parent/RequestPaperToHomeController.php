<?php

namespace App\Http\Controllers\Api\Parent;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Parent\RequestPaperToHomeRequest;
use App\Models\BillingInformation;
use App\Models\PaperRequestActivityLog;
use App\Models\RequestedPaperToHome;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class RequestPaperToHomeController extends Controller
{
    /**
     * Store or update a paper request in storage.
     * If a request already exists for the same parent and paper, it will be updated.
     */
    public function store(RequestPaperToHomeRequest $request)
    {
        try {
            $parentId = Auth::id();
            $paperId = $request->input('paper_id');
            $billingInfoId = $request->input('billing_information_id');

            // Check if a request already exists (including soft-deleted)
            $existingRequest = RequestedPaperToHome::withTrashed()
                ->where('parent_id', $parentId)
                ->where('paper_id', $paperId)
                ->first();

            if ($existingRequest) {
                // Load old billing information details
                $oldBillingInfo = BillingInformation::find($existingRequest->billing_information_id);
                
                // Store old values for activity log with full billing details
                $oldValues = [
                    'billing_information_id' => $existingRequest->billing_information_id,
                    'billing_information' => $oldBillingInfo ? [
                        'address_line1' => $oldBillingInfo->address_line1,
                        'address_line2' => $oldBillingInfo->address_line2,
                        'city' => $oldBillingInfo->city,
                        'state' => $oldBillingInfo->state,
                        'postal_code' => $oldBillingInfo->postal_code,
                        'country' => $oldBillingInfo->country,
                        'phone' => $oldBillingInfo->phone,
                    ] : null,
                    'requested' => $existingRequest->requested,
                    'deleted_at' => $existingRequest->deleted_at,
                ];

                // Update existing request
                $wasRestored = $existingRequest->trashed();
                $existingRequest->billing_information_id = $billingInfoId;
                $existingRequest->requested = true;
                
                // Restore if soft-deleted
                if ($wasRestored) {
                    $existingRequest->restore();
                }
                
                $existingRequest->save();
                $existingRequest->refresh();

                // Load new billing information details
                $newBillingInfo = BillingInformation::find($billingInfoId);
                
                // Store new values for activity log with full billing details
                $newValues = [
                    'billing_information_id' => $existingRequest->billing_information_id,
                    'billing_information' => $newBillingInfo ? [
                        'address_line1' => $newBillingInfo->address_line1,
                        'address_line2' => $newBillingInfo->address_line2,
                        'city' => $newBillingInfo->city,
                        'state' => $newBillingInfo->state,
                        'postal_code' => $newBillingInfo->postal_code,
                        'country' => $newBillingInfo->country,
                        'phone' => $newBillingInfo->phone,
                    ] : null,
                    'requested' => $existingRequest->requested,
                    'deleted_at' => $existingRequest->deleted_at,
                ];

                // Generate description with billing details
                $oldAddress = $oldBillingInfo ? $this->formatAddress($oldBillingInfo) : 'N/A';
                $newAddress = $newBillingInfo ? $this->formatAddress($newBillingInfo) : 'N/A';
                
                $description = $wasRestored 
                    ? "Paper request restored and billing information updated from: {$oldAddress} to: {$newAddress}"
                    : "Paper request billing information updated from: {$oldAddress} to: {$newAddress}";

                // Log the update activity
                $this->logActivity(
                    $existingRequest->id,
                    $parentId,
                    $paperId,
                    $billingInfoId,
                    $wasRestored ? 'restored' : 'updated',
                    $description,
                    $oldValues,
                    $newValues,
                    $request
                );

                return sendResponse(
                    $existingRequest, 
                    'Paper request updated successfully. We are processing your request with the new billing information.', 
                    200
                );
            }

            // Create new request if it doesn't exist
            $requestedPaper = RequestedPaperToHome::create([
                'parent_id' => $parentId,
                'paper_id' => $paperId,
                'billing_information_id' => $billingInfoId,
                'requested' => true,
            ]);

            // Load billing information details
            $billingInfo = BillingInformation::find($billingInfoId);
            
            // Log the create activity with full billing details
            $newValues = [
                'parent_id' => $requestedPaper->parent_id,
                'paper_id' => $requestedPaper->paper_id,
                'billing_information_id' => $requestedPaper->billing_information_id,
                'billing_information' => $billingInfo ? [
                    'address_line1' => $billingInfo->address_line1,
                    'address_line2' => $billingInfo->address_line2,
                    'city' => $billingInfo->city,
                    'state' => $billingInfo->state,
                    'postal_code' => $billingInfo->postal_code,
                    'country' => $billingInfo->country,
                    'phone' => $billingInfo->phone,
                ] : null,
                'requested' => $requestedPaper->requested,
            ];

            $address = $billingInfo ? $this->formatAddress($billingInfo) : 'N/A';
            $description = "New paper request created for paper ID {$paperId} with billing information: {$address}";

            $this->logActivity(
                $requestedPaper->id,
                $parentId,
                $paperId,
                $billingInfoId,
                'created',
                $description,
                null,
                $newValues,
                $request
            );

            return sendResponse($requestedPaper, 'Paper request submitted successfully. We are processing your request.', 201);
        } catch (Exception $e) {
            Log::error("Failed to create/update paper request: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
            return sendError('An error occurred while submitting paper request', [], 500);
        }
    }

    /**
     * Log activity for paper request actions.
     *
     * @param int $requestedPaperId
     * @param int $userId
     * @param int $paperId
     * @param int $billingInfoId
     * @param string $action
     * @param string $description
     * @param array|null $oldValues
     * @param array|null $newValues
     * @param \Illuminate\Http\Request $request
     * @return void
     */
    private function logActivity(
        int $requestedPaperId,
        int $userId,
        int $paperId,
        int $billingInfoId,
        string $action,
        string $description,
        ?array $oldValues,
        ?array $newValues,
        $request
    ): void {
        try {
            PaperRequestActivityLog::create([
                'requested_paper_to_home_id' => $requestedPaperId,
                'user_id' => $userId,
                'paper_id' => $paperId,
                'billing_information_id' => $billingInfoId,
                'action' => $action,
                'description' => $description,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (Exception $e) {
            // Log error but don't fail the main request
            Log::error("Failed to log paper request activity: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    /**
     * Format billing address for description.
     *
     * @param BillingInformation $billingInfo
     * @return string
     */
    private function formatAddress(BillingInformation $billingInfo): string
    {
        $parts = array_filter([
            $billingInfo->address_line1,
            $billingInfo->address_line2,
            $billingInfo->city,
            $billingInfo->state,
            $billingInfo->postal_code,
            $billingInfo->country,
        ]);

        return !empty($parts) ? implode(', ', $parts) : 'No address provided';
    }
}
