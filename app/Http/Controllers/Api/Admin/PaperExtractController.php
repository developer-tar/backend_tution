<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaperExtract;
use App\Models\PaperExtractQuestion;
use App\Models\MockExamCategory;
use App\Models\Format;
use App\Services\PaperExtractionService;
use App\Jobs\ExtractPaperJob;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PaperExtractController extends Controller
{
    protected $extractionService;

    public function __construct(PaperExtractionService $extractionService)
    {
        $this->extractionService = $extractionService;
    }

    /**
     * Display a listing of paper extracts
     */
    public function index(Request $request)
    {
        try {
            $query = PaperExtract::with([
                'creator:id,first_name,last_name,email',
            ])
                ->withCount([
                    'questions' => function ($q) {
                        $q->whereNull('deleted_at');
                    }
                ]);

            // Apply filters (only if value is not empty)
            if ($request->filled('search')) {
                $query->search($request->search);
            }

            if ($request->filled('subject')) {
                $query->bySubject($request->subject);
            }

            if ($request->filled('year')) {
                $query->byYear($request->year);
            }

            if ($request->filled('level')) {
                $query->byLevel($request->level);
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('extraction_status')) {
                $query->byExtractionStatus($request->extraction_status);
            }

            // Pagination
            $perPage = $request->get('per_page', 15);
            $extracts = $query->whereNull('deleted_at')->latest()->paginate($perPage);

            return sendResponse($extracts, 'Paper extracts retrieved successfully.');
        } catch (Exception $e) {
            return errorLog("Failed to fetch paper extracts: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    /**
     * Store a newly created paper extract
     * 
     */
    public function store(Request $request)
    {
        Log::debug('PaperExtract store request', $request->all());

        try {
            $validated = $request->validate([
                'paper_id' => 'nullable|exists:papers,id',
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'source_file' => 'required|file|mimes:pdf,doc,docx|max:10240', // 10MB max
                'category_id' => 'nullable|exists:mock_exam_categories,id',
                'format_id' => 'nullable|exists:formats,id',
                'subject' => 'nullable|string|max:100',
                'exam_board' => 'nullable|string|max:100',
                'year' => 'nullable|string|max:20',
                'level' => 'nullable|string|max:50',
                'price' => 'nullable|numeric|min:0',
                'currency' => 'nullable|string|max:10',
            ]);

            DB::beginTransaction();

            // Store the uploaded file
            $file = $request->file('source_file');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('paper_extracts/source', $fileName, 'public');

            // Get category_id if provided, otherwise get default category
            $categoryId = $validated['category_id'] ?? null;
            if (!$categoryId) {
                // Try to get a default category (first root category)
                $defaultCategory = MockExamCategory::whereNull('parent_id')->first();
                if (!$defaultCategory) {
                    // If no categories exist, create a default one for extracted papers
                    $defaultCategory = MockExamCategory::create([
                        'name' => 'Extracted Papers',
                        'parent_id' => null,
                        'status' => config('constants.statuses.APPROVED'),
                    ]);
                }
                $categoryId = $defaultCategory->id;
            }

            // Get format_id if provided, otherwise get default format
            $formatId = $validated['format_id'] ?? null;
            if (!$formatId) {
                // Try to get a default format (first available format or 'PDF')
                $defaultFormat = Format::where('name', 'PDF')->first();
                if (!$defaultFormat) {
                    $defaultFormat = Format::first();
                }
                if (!$defaultFormat) {
                    // If no formats exist, create a default one
                    $defaultFormat = Format::create([
                        'name' => 'PDF',
                    ]);
                }
                $formatId = $defaultFormat->id;
            }

            // Generate unique slug
            $baseSlug = Str::slug($validated['name']);
            $slug = $baseSlug;
            $counter = 1;
            while (PaperExtract::withTrashed()->where('slug', $slug)->exists()) {
                $slug = $baseSlug . '-' . $counter++;
            }

            // Create paper extract record
            $paperExtract = PaperExtract::create([
                'paper_id' => $validated['paper_id'] ?? null,
                'title' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'category_id' => $categoryId,
                'format_id' => $formatId,
                'price' => $validated['price'] ?? null,
                'currency' => $validated['currency'] ?? '€',
                'source_file_path' => $filePath,
                'extraction_status' => 'pending',
                'subject' => $validated['subject'] ?? null,
                'exam_board' => $validated['exam_board'] ?? null,
                'year' => $validated['year'] ?? null,
                'level' => $validated['level'] ?? null,
                'status' => config('constants.statuses.APPROVED'),
                'slug' => $slug,
                'created_by' => auth()->id(),
            ]);

            DB::commit();

            // Run extraction via ExtractPaperJob (synchronously so we can return extracted data)
            $fullPath = Storage::disk('public')->path($filePath);
            try {
                $job = new ExtractPaperJob($paperExtract->id, null, $fullPath, null);
                Bus::dispatchSync($job);
            } catch (Exception $e) {
                Log::error("Extraction failed: {$e->getMessage()}");
                $paperExtract->update([
                    'extraction_status' => 'failed',
                    'extraction_error' => $e->getMessage(),
                ]);
            }

            $paperExtract->load(['creator', 'questions']);

            return sendResponse($paperExtract, 'Paper extract created successfully.', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return sendError('Validation failed', $e->errors(), 422);
        } catch (Exception $e) {
            DB::rollBack();
            return errorLog("Failed to create paper extract: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    /**
     * Display the specified paper extract
     */
    public function show($id)
    {
        try {
            $paperExtract = PaperExtract::with([
                'creator:id,first_name,last_name,email',
            ])
                ->findOrFail($id);

            // Load questions with media
            $paperExtract->load([
                'questions' => function ($query) {
                    $query->whereNull('deleted_at')
                        ->orderByRaw('COALESCE(section, "") ASC, question_number ASC');
                },
                'questions.media' => function ($query) {
                    $query->whereNull('deleted_at')->orderBy('position');
                }
            ]);

            return sendResponse($paperExtract, 'Paper extract retrieved successfully.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return sendError('Error', ['error' => 'Paper extract not found.'], 404);
        } catch (Exception $e) {
            return errorLog("Failed to fetch paper extract: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    /**
     * Update the specified paper extract
     */
    public function update(Request $request, $id)
    {
        try {
            $paperExtract = PaperExtract::findOrFail($id);

            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'subject' => 'nullable|string|max:100',
                'exam_board' => 'nullable|string|max:100',
                'year' => 'nullable|string|max:20',
                'level' => 'nullable|string|max:50',
                'price' => 'nullable|numeric|min:0',
                'currency' => 'nullable|string|max:10',
                'status' => 'sometimes|integer',
            ]);

            // Update slug if name changed
            if (isset($validated['name']) && $validated['name'] !== $paperExtract->name) {
                $baseSlug = Str::slug($validated['name']);
                $slug = $baseSlug;
                $counter = 1;
                while (PaperExtract::withTrashed()->where('slug', $slug)->where('id', '!=', $paperExtract->id)->exists()) {
                    $slug = $baseSlug . '-' . $counter++;
                }
                $validated['slug'] = $slug;
            }

            $paperExtract->update($validated);
            $paperExtract->load(['creator', 'questions']);

            return sendResponse($paperExtract, 'Paper extract updated successfully.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return sendError('Error', ['error' => 'Paper extract not found.'], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return sendError('Validation failed', $e->errors(), 422);
        } catch (Exception $e) {
            return errorLog("Failed to update paper extract: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    /**
     * Remove the specified paper extract
     */
    public function destroy($id)
    {
        try {
            $paperExtract = PaperExtract::findOrFail($id);

            // Delete associated files
            if ($paperExtract->source_file_path && Storage::disk('public')->exists($paperExtract->source_file_path)) {
                Storage::disk('public')->delete($paperExtract->source_file_path);
            }

            if ($paperExtract->extracted_file_path && Storage::disk('public')->exists($paperExtract->extracted_file_path)) {
                Storage::disk('public')->delete($paperExtract->extracted_file_path);
            }

            $paperExtract->delete();

            return sendResponse('delete', 'Paper extract deleted successfully.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return sendError('Error', ['error' => 'Paper extract not found.'], 404);
        } catch (Exception $e) {
            return errorLog("Failed to delete paper extract: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    /**
     * Search questions within paper extracts
     */
    public function searchQuestions(Request $request)
    {
        try {
            $validated = $request->validate([
                'search' => 'required|string|min:2',
                'paper_extract_id' => 'nullable|exists:paper_extracts,id',
                'question_type' => 'nullable|string',
                'topic' => 'nullable|string',
                'subject' => 'nullable|string',
            ]);

            $query = PaperExtractQuestion::with('paperExtract')
                ->where('status', config('constants.statuses.APPROVED'));

            // Search in question text
            $query->search($validated['search']);

            // Apply filters
            if (isset($validated['paper_extract_id'])) {
                $query->where('paper_extract_id', $validated['paper_extract_id']);
            }

            if (isset($validated['question_type'])) {
                $query->byType($validated['question_type']);
            }

            if (isset($validated['topic'])) {
                $query->byTopic($validated['topic']);
            }

            if (isset($validated['subject'])) {
                $query->whereHas('paperExtract', function ($q) use ($validated) {
                    $q->where('subject', $validated['subject']);
                });
            }

            $perPage = $request->get('per_page', 20);
            $questions = $query->orderBy('question_number')->paginate($perPage);

            return sendResponse($questions, 'Questions retrieved successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return sendError('Validation failed', $e->errors(), 422);
        } catch (Exception $e) {
            return errorLog("Failed to search questions: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    /**
     * Get statistics about paper extracts
     */
    public function statistics()
    {
        try {
            $stats = [
                'total_extracts' => PaperExtract::count(),
                'completed_extracts' => PaperExtract::where('extraction_status', 'completed')->count(),
                'pending_extracts' => PaperExtract::where('extraction_status', 'pending')->count(),
                'processing_extracts' => PaperExtract::where('extraction_status', 'processing')->count(),
                'failed_extracts' => PaperExtract::where('extraction_status', 'failed')->count(),
                'total_questions' => PaperExtractQuestion::count(),
                'subjects' => PaperExtract::select('subject')
                    ->whereNotNull('subject')
                    ->distinct()
                    ->pluck('subject'),
                'years' => PaperExtract::select('year')
                    ->whereNotNull('year')
                    ->distinct()
                    ->pluck('year'),
            ];

            return sendResponse($stats, 'Statistics retrieved successfully.');
        } catch (Exception $e) {
            return errorLog("Failed to fetch statistics: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    /**
     * Update a specific question's answer and explanation
     */
    public function updateQuestion(Request $request, $id)
    {
        try {
            $question = PaperExtractQuestion::whereNull('deleted_at')
                ->findOrFail($id);

            $validated = $request->validate([
                'correct_answer' => 'nullable|string|max:255',
                'answer_explanation' => 'nullable|string',
            ]);

            $question->update($validated);
            $question->load('media');

            return sendResponse($question, 'Question updated successfully.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return sendError('Error', ['error' => 'Question not found.'], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return sendError('Validation failed', $e->errors(), 422);
        } catch (Exception $e) {
            return errorLog("Failed to update question: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    public function upload(Request $request)
    {
        $path = $request->file('paper')->store('papers');

        $paper = PaperExtract::create([
            'file_path' => $path,
            'status' => 'processing'
        ]);

        ExtractPaperJob::dispatch(
            $paper->id,
            null,
            storage_path('app/' . $path),
            null
        );

        return response()->json([
            'message' => 'Paper uploaded. Extraction started.'
        ]);
    }

}
