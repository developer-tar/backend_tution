<?php

namespace App\Http\Controllers\Api\Parent;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\StudentDetail;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class CertificateController extends Controller
{
    /**
     * Get all certificates for the authenticated parent's children
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

            // Get all children (students) for this parent
            $children = StudentDetail::where('parent_id', $user->id)
                ->pluck('child_id')
                ->toArray();

            if (empty($children)) {
                return response()->json([
                    'success' => true,
                    'message' => 'No children found for this parent.',
                    'data' => [],
                ], 200);
            }

            // Get certificates for all children
            $certificates = Certificate::with(['award', 'student'])
                ->whereIn('student_id', $children)
                ->where('status', 1) // Only active certificates
                ->latest('issued_date')
                ->get();

            // Group certificates by student for better organization
            $groupedCertificates = $certificates->groupBy('student_id')->map(function ($studentCertificates) {
                $student = $studentCertificates->first()->student;
                return [
                    'student' => [
                        'id' => $student->id,
                        'name' => $student->full_name,
                        'email' => $student->email,
                    ],
                    'certificates' => $studentCertificates->map(function ($certificate) {
                        return [
                            'id' => $certificate->id,
                            'certificate_number' => $certificate->certificate_number,
                            'award' => [
                                'id' => $certificate->award->id,
                                'name' => $certificate->award->name,
                                'description' => $certificate->award->description,
                            ],
                            'issued_date' => $certificate->issued_date->format('Y-m-d'),
                            'achievement_details' => $certificate->achievement_details,
                            'pdf_path' => $certificate->pdf_path,
                        ];
                    }),
                ];
            })->values();

            return response()->json([
                'success' => true,
                'message' => 'Certificates fetched successfully.',
                'data' => $groupedCertificates,
                'all_certificates' => $certificates, // Also include flat list for compatibility
            ], 200);
        } catch (\Exception $e) {
            return errorLog("Failed to fetch certificates: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    /**
     * Get a specific certificate for the authenticated parent's child
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

            // Get all children (students) for this parent
            $children = StudentDetail::where('parent_id', $user->id)
                ->pluck('child_id')
                ->toArray();

            if (empty($children)) {
                return sendError('error', ['error' => 'No children found for this parent.'], 404);
            }

            $certificate = Certificate::with(['award', 'student'])
                ->where('id', $id)
                ->whereIn('student_id', $children)
                ->where('status', 1)
                ->firstOrFail();

            return response()->json([
                'success' => true,
                'message' => 'Certificate fetched successfully.',
                'data' => $certificate,
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return sendError('error', ['error' => 'Certificate not found or you do not have access to it.'], 404);
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

            // Get all children (students) for this parent
            $children = StudentDetail::where('parent_id', $user->id)
                ->pluck('child_id')
                ->toArray();

            if (empty($children)) {
                return sendError('error', ['error' => 'No children found for this parent.'], 404);
            }

            $certificate = Certificate::where('id', $id)
                ->whereIn('student_id', $children)
                ->where('status', 1)
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
            return sendError('error', ['error' => 'Certificate not found or you do not have access to it.'], 404);
        } catch (\Exception $e) {
            return errorLog("Failed to download certificate: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }
}

