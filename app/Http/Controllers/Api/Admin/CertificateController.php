<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\IndexCertificateRequest;
use App\Http\Requests\Api\Admin\StoreCertificateRequest;
use App\Http\Requests\Api\Admin\ShowCertificateRequest;
use App\Http\Requests\Api\Admin\DownloadCertificateRequest;
use App\Http\Requests\Api\Admin\RevokeCertificateRequest;
use App\Http\Requests\Api\Admin\DestroyCertificateRequest;
use App\Models\Certificate;
use App\Models\Award;
use App\Models\User;
use App\Models\Role;
use App\Services\CertificateGenerationService;
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
     * @param IndexCertificateRequest $request
     * @return JsonResponse
     */
    public function index(IndexCertificateRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $search = $validated['search'] ?? null;
            $awardId = $validated['award_id'] ?? null;
            $studentId = $validated['student_id'] ?? null;
            $status = $validated['status'] ?? null;
            $perPage = $validated['per_page'] ?? 10;

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

            return sendResponse($certificates, 'Certificates fetched successfully.', 200);
        } catch (\Exception $e) {
            return errorLog("Failed to fetch certificates: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    /**
     * Generate and store a new certificate for a student.
     *
     * @param StoreCertificateRequest $request
     * @return JsonResponse
     */
    public function store(StoreCertificateRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

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
                return sendError('Certificate already exists for this student and award.', $existingCertificate, 409);
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

            return sendResponse($certificate, 'Certificate generated successfully.', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return sendError('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return errorLog("Failed to create certificate: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    /**
     * Display the specified certificate.
     *
     * @param ShowCertificateRequest $request
     * @return JsonResponse
     */
    public function show(ShowCertificateRequest $request): JsonResponse
    {
        try {
            $id = $request->route('certificate') ?? $request->route('id');
            $certificate = Certificate::with(['award', 'student'])->findOrFail($id);

            return sendResponse($certificate, 'Certificate fetched successfully.', 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return sendError('error', ['error' => 'Certificate not found.'], 404);
        } catch (\Exception $e) {
            return errorLog("Failed to fetch certificate: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    /**
     * Download the certificate PDF.
     *
     * @param DownloadCertificateRequest $request
     * @return \Illuminate\Http\Response
     */
    public function download(DownloadCertificateRequest $request)
    {
        try {
            $id = $request->route('id');
            $certificate = Certificate::findOrFail($id);

            if (!$certificate->pdf_path || !Storage::exists($certificate->pdf_path)) {
                // Check if DomPDF is installed before trying to regenerate
                if (!class_exists('Barryvdh\DomPDF\Facade\Pdf')) {
                    return sendError('DomPDF package is not installed', [
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
                return sendError('DomPDF package is not installed', [
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
     * @param RevokeCertificateRequest $request
     * @return JsonResponse
     */
    public function revoke(RevokeCertificateRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $id = $request->route('id');
            $certificate = Certificate::findOrFail($id);
            $certificate->update(['status' => 0]);

            DB::commit();

            return sendResponse('delete', 'Certificate revoked successfully.', 200);
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
     * @param DestroyCertificateRequest $request
     * @return JsonResponse
     */
    public function destroy(DestroyCertificateRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $id = $request->route('certificate') ?? $request->route('id');
            $certificate = Certificate::findOrFail($id);

            // Delete PDF file if exists
            if ($certificate->pdf_path && Storage::exists($certificate->pdf_path)) {
                Storage::delete($certificate->pdf_path);
            }

            $certificate->delete();

            DB::commit();

            return sendResponse('delete', 'Certificate deleted successfully.', 200);
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

            return sendResponse($students, 'Students fetched successfully.', 200);
        } catch (\Exception $e) {
            return errorLog("Failed to fetch students: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }
}
