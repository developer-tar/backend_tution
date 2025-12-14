<?php

namespace App\Http\Controllers\Api\Parent;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Parent\RequestPaperToHomeRequest;
use App\Models\RequestedPaperToHome;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class RequestPaperToHomeController extends Controller
{
    /**
     * Store a newly created paper request in storage.
     */
    public function store(RequestPaperToHomeRequest $request)
    {
        try {
            $parentId = Auth::id();

            // All validation is handled in RequestPaperToHomeRequest
            $requestedPaper = RequestedPaperToHome::create([
                'parent_id' => $parentId,
                'paper_id' => $request->input('paper_id'),
                'billing_information_id' => $request->input('billing_information_id'),
                'requested' => true,
            ]);

            return sendResponse($requestedPaper, 'Paper request submitted successfully. We are processing your request.', 201);
        } catch (Exception $e) {
            Log::error("Failed to create paper request: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
            return sendError('An error occurred while submitting paper request', [], 500);
        }
    }
}
