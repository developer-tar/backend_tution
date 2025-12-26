<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\Award;
use App\Models\User;
use App\Models\Role;
use App\Services\CertificateGenerationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CertificateController extends Controller
{
    protected $certificateService;

    public function __construct(CertificateGenerationService $certificateService)
    {
        $this->certificateService = $certificateService;
    }

    /**
     * Display a listing of certificates.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $search = $request->input('search');
            $awardId = $request->input('award_id');
            $studentId = $request->input('student_id');
            $status = $request->input('status');
            $perPage = $request->input('per_page', 10);

            $certificates = Certificate::with(['award', 'student'])
                ->when($search, function ($query) use ($search) {
                    $query->where(function ($q) use ($search) {
                        $q->where('certificate_number', 'LIKE', "%{$search}%")
                            ->orWhereHas('student', function ($studentQuery) use ($search) {
                                $studentQuery->where('first_name', 'LIKE', "%{$search}%")
                                    ->orWhere('last_name', 'LIKE', "%{$search}%")
                                    ->orWhere('email', 'LIKE', "%{$search}%");
                            })
                            ->orWhereHas('award', function ($awardQuery) use ($search) {
                                $awardQuery->where('name', 'LIKE', "%{$search}%");
                            });
                    });
                })
                ->when($awardId, function ($query) use ($awardId) {
                    $query->where('award_id', $awardId);
                })
                ->when($studentId, function ($query) use ($studentId) {
                    $query->where('student_id', $studentId);
                })
                ->when($status !== null, function ($query) use ($status) {
                    $query->where('status', $status);
                })
                ->latest()
                ->paginate($perPage);

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
     * Generate and store a new certificate for a student.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'award_id' => 'required|exists:awards,id',
                'student_id' => 'required|exists:users,id',
                'issued_date' => 'nullable|date',
                'achievement_details' => 'nullable|string',
            ]);

            DB::beginTransaction();

            $award = Award::findOrFail($validated['award_id']);
            $student = User::findOrFail($validated['student_id']);

            // Check if certificate already exists for this award and student
            $existingCertificate = Certificate::where('award_id', $award->id)
                ->where('student_id', $student->id)
                ->where('status', 1)
                ->first();

            if ($existingCertificate) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Certificate already exists for this student and award.',
                    'data' => $existingCertificate,
                ], 409);
            }

            // Generate certificate number
            $certificateNumber = Certificate::generateCertificateNumber();

            // Create certificate record
            $certificate = Certificate::create([
                'award_id' => $award->id,
                'student_id' => $student->id,
                'certificate_number' => $certificateNumber,
                'issued_date' => $validated['issued_date'] ?? now()->toDateString(),
                'achievement_details' => $validated['achievement_details'] ?? null,
                'status' => 1,
            ]);

            // Generate PDF certificate
            try {
                $pdfPath = $this->certificateService->generateCertificate($certificate);
                $certificate->update(['pdf_path' => $pdfPath]);
            } catch (\Exception $e) {
                // Log error but don't fail the certificate creation
                errorLog("Failed to generate PDF for certificate {$certificate->id}: {$e->getMessage()}");
            }

            DB::commit();

            $certificate->load(['award', 'student']);

            return response()->json([
                'success' => true,
                'message' => 'Certificate generated successfully.',
                'data' => $certificate,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return errorLog("Failed to create certificate: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    /**
     * Display the specified certificate.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show($id): JsonResponse
    {
        try {
            $certificate = Certificate::with(['award', 'student'])->findOrFail($id);

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
     * Download the certificate PDF.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function download($id)
    {
        try {
            $certificate = Certificate::findOrFail($id);

            if (!$certificate->pdf_path || !Storage::exists($certificate->pdf_path)) {
                // Check if DomPDF is installed before trying to regenerate
                if (!class_exists('Barryvdh\DomPDF\Facade\Pdf')) {
                    return response()->json([
                        'success' => false,
                        'message' => 'DomPDF package is not installed',
                        'error' => 'Please install DomPDF package by running: composer require barryvdh/laravel-dompdf in the backend_tution directory, then run: composer install',
                        'instructions' => [
                            '1. Open terminal/command prompt',
                            '2. Navigate to: cd backend_tution',
                            '3. Run: composer require barryvdh/laravel-dompdf',
                            '4. Or if already in composer.json, run: composer install',
                            '5. Clear config: php artisan config:clear',
                        ]
                    ], 500);
                }

                // Regenerate if PDF doesn't exist
                $pdfPath = $this->certificateService->generateCertificate($certificate);
                $certificate->update(['pdf_path' => $pdfPath]);
            }

            return Storage::download($certificate->pdf_path, "certificate-{$certificate->certificate_number}.pdf");
        } catch (\Exception $e) {
            // Check if error is about DomPDF not being installed
            if (str_contains($e->getMessage(), 'DomPDF package is not installed')) {
                return response()->json([
                    'success' => false,
                    'message' => 'DomPDF package is not installed',
                    'error' => $e->getMessage(),
                    'instructions' => [
                        '1. Open terminal/command prompt',
                        '2. Navigate to: cd backend_tution',
                        '3. Run: composer require barryvdh/laravel-dompdf',
                        '4. Or if already in composer.json, run: composer install',
                        '5. Clear config: php artisan config:clear',
                    ]
                ], 500);
            }

            return errorLog("Failed to download certificate: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    /**
     * Revoke a certificate.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function revoke($id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $certificate = Certificate::findOrFail($id);
            $certificate->update(['status' => 0]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Certificate revoked successfully.',
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return sendError('error', ['error' => 'Certificate not found.'], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            return errorLog("Failed to revoke certificate: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    /**
     * Delete the specified certificate.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $certificate = Certificate::findOrFail($id);

            // Delete PDF file if exists
            if ($certificate->pdf_path && Storage::exists($certificate->pdf_path)) {
                Storage::delete($certificate->pdf_path);
            }

            $certificate->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Certificate deleted successfully.',
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return sendError('error', ['error' => 'Certificate not found.'], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            return errorLog("Failed to delete certificate: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    /**
     * Get all students (users with student role) for certificate generation
     *
     * @return JsonResponse
     */
    public function getStudents(): JsonResponse
    {
        try {
            $students = User::whereHas('roles', function ($query) {
                $query->where('name', config('constants.roles.STUDENT'));
            })
                ->select('id', 'first_name', 'last_name', 'email')
                ->orderBy('first_name', 'asc')
                ->orderBy('last_name', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Students fetched successfully.',
                'data' => $students,
            ], 200);
        } catch (\Exception $e) {
            return errorLog("Failed to fetch students: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }
}
