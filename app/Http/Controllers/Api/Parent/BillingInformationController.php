<?php

namespace App\Http\Controllers\Api\Parent;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Parent\StoreBillingInformationRequest;
use App\Http\Requests\Api\Parent\UpdateBillingInformationRequest;
use App\Models\BillingInformation;
use App\Models\RequestedPaperToHome;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class BillingInformationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $parentId = Auth::id();
            
            $billingInformations = BillingInformation::where('parent_id', $parentId)
                ->orderBy('created_at', 'desc')
                ->get();

            return sendResponse($billingInformations, 'Billing information fetched successfully.');
        } catch (Exception $e) {
            Log::error("Failed to fetch billing information: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
            return sendError('An error occurred while fetching billing information', [], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreBillingInformationRequest $request)
    {
        try {
            $parentId = Auth::id();

            $billingInformation = BillingInformation::create([
                'parent_id' => $parentId,
                'address_line1' => $request->input('address_line1'),
                'address_line2' => $request->input('address_line2'),
                'city' => $request->input('city'),
                'state' => $request->input('state'),
                'postal_code' => $request->input('postal_code'),
                'country' => $request->input('country'),
                'phone' => $request->input('phone'),
            ]);

            return sendResponse($billingInformation, 'Billing information created successfully.', 201);
        } catch (Exception $e) {
            Log::error("Failed to create billing information: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
            return sendError('An error occurred while creating billing information', [], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateBillingInformationRequest $request)
    {
        try {
            $parentId = Auth::id();
            $billingInfoId = $request->input('id');

            $billingInformation = BillingInformation::where('id', $billingInfoId)
                ->where('parent_id', $parentId)
                ->firstOrFail();

            $updateData = [];
            if ($request->has('address_line1')) {
                $updateData['address_line1'] = $request->input('address_line1');
            }
            if ($request->has('address_line2')) {
                $updateData['address_line2'] = $request->input('address_line2');
            }
            if ($request->has('city')) {
                $updateData['city'] = $request->input('city');
            }
            if ($request->has('state')) {
                $updateData['state'] = $request->input('state');
            }
            if ($request->has('postal_code')) {
                $updateData['postal_code'] = $request->input('postal_code');
            }
            if ($request->has('country')) {
                $updateData['country'] = $request->input('country');
            }
            if ($request->has('phone')) {
                $updateData['phone'] = $request->input('phone');
            }

            $billingInformation->update($updateData);
            $billingInformation->refresh();

            return sendResponse($billingInformation, 'Billing information updated successfully.');
        } catch (Exception $e) {
            Log::error("Failed to update billing information: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
            return sendError('An error occurred while updating billing information', [], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $parentId = Auth::id();

            $billingInformation = BillingInformation::where('id', $id)
                ->where('parent_id', $parentId)
                ->firstOrFail();

            // Check if billing information is being used in requested_papers_to_home table
            $isInUse = RequestedPaperToHome::withTrashed()
                ->where('billing_information_id', $id)
                ->exists();

            if ($isInUse) {
                return sendError(
                    'This billing information cannot be deleted because it is currently being used in a paper request. Please remove or update the paper request first.',
                    [],
                    422
                );
            }

            $billingInformation->delete();

            return sendResponse([], 'Billing information deleted successfully.');
        } catch (Exception $e) {
            Log::error("Failed to delete billing information: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
            return sendError('An error occurred while deleting billing information', [], 500);
        }
    }
}
