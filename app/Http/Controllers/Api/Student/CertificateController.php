<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class CertificateController extends Controller
{
    /**
     * Get all certificates for the authenticated student
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return sendError('error', ['error' => 'Unauthorized.'], 401);
            }

            $certificates = Certificate::with(['award'])
                ->where(['student_id', $user->id , 'status', 1]) // Only active certificates
                ->latest('issued_date')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Certificates fetched successfully.',
                'data' => $certificates,
            ], 200);
        } catch (\Exception $e) {
            return errorLog("Failed to fetch certificates: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    /**
     * Get a specific certificate for the authenticated student
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show($id): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return sendError('error', ['error' => 'Unauthorized.'], 401);
            }

            $certificate = Certificate::with(['award'])
                ->where('id', $id)
                ->where('student_id', $user->id)
                ->where('status', 1)
                ->firstOrFail();

            return response()->json([
                'success' => true,
                'message' => 'Certificate fetched successfully.',
                'data' => $certificate,
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return sendError('error', ['error' => 'Certificate not found.'], 404);
        } catch (\Exception $e) {
            return errorLog("Failed to fetch certificate: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    /**
     * Download the certificate PDF
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function download($id)
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return sendError('error', ['error' => 'Unauthorized.'], 401);
            }

            $certificate = Certificate::where('id', $id)
                ->where(['student_id', $user->id,'status', 1])
                ->firstOrFail();

            if (!$certificate->pdf_path || !Storage::exists($certificate->pdf_path)) {
                // Check if DomPDF is installed before trying to regenerate
                if (!class_exists('Barryvdh\DomPDF\Facade\Pdf')) {
                    return sendError('error', [
                        'error' => 'Certificate PDF cannot be generated. DomPDF package is not installed.',
                        'message' => 'Please contact administrator to install DomPDF package.'
                    ], 500);
                }
                
                // Try to regenerate if PDF doesn't exist
                try {
                    $certificateService = app(\App\Services\CertificateGenerationService::class);
                    $pdfPath = $certificateService->generateCertificate($certificate);
                    $certificate->update(['pdf_path' => $pdfPath]);
                } catch (\Exception $e) {
                    return sendError('error', [
                        'error' => 'Certificate PDF not found and cannot be generated.',
                        'message' => $e->getMessage()
                    ], 500);
                }
            }

            return Storage::download($certificate->pdf_path, "certificate-{$certificate->certificate_number}.pdf");
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return sendError('error', ['error' => 'Certificate not found.'], 404);
        } catch (\Exception $e) {
            return errorLog("Failed to download certificate: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }
}
