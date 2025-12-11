<?php

namespace App\Http\Controllers\Api\Admin\Assign;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\StorePaperRequest;
use App\Http\Requests\Api\Admin\UpdatePaperRequest;
use App\Http\Requests\Api\Admin\TogglePaperStatusRequest;
use App\Jobs\PaperPrice;
use App\Jobs\UploadPaperImageJob;
use App\Jobs\UpdatePaperStripePrice;
use App\Models\Paper;
use App\Models\PaperAnswer;
use App\Models\MockExamCategory;
use App\Models\PaperOption;
use App\Models\PaperPurchase;
use App\Models\PaperQuestion;
use App\Models\PaperUserAnswer;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaperController extends Controller
{
    public function getCategories(Request $request)
    {
        try {
            $parentId = $request->input('parent_id');

            $categories = MockExamCategory::with('children')
                ->when($parentId !== null, fn($q) => $q->where('parent_id', $parentId), fn($q) => $q->whereNull('parent_id'))
                ->orderBy('name')
                ->get()
                ->map(function ($category) {
                    return [
                        'id' => $category->id,
                        'name' => $category->name,
                        'has_children' => $category->children->isNotEmpty(),
                        'children_count' => $category->children->count(),
                    ];
                });

            return sendResponse($categories, 'Categories fetched successfully');
        } catch (Exception $e) {
            return errorLog("Failed to fetch categories: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    public function getCategoryTree()
    {
        try {
            $categories = MockExamCategory::with('allChildren')
                ->whereNull('parent_id')
                ->orderBy('name')
                ->get();

            return sendResponse($categories, 'Category tree fetched successfully');
        } catch (Exception $e) {
            return errorLog("Failed to fetch category tree: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    public function storeCategory(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:mock_exam_categories,id',
        ]);

        try {
            DB::beginTransaction();

            $category = MockExamCategory::create([
                'name' => $request->name,
                'parent_id' => $request->parent_id,
            ]);

            DB::commit();

            return sendResponse($category, 'Category created successfully', 201);
        } catch (Exception $e) {
            DB::rollBack();
            return errorLog("Failed to create category: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    public function updateCategory(Request $request, $id)
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'parent_id' => 'nullable|exists:mock_exam_categories,id',
        ]);

        try {
            DB::beginTransaction();
            
            $category = MockExamCategory::findOrFail($id);
            if ($request->has('parent_id') && $request->parent_id == $category->id) {
                DB::rollBack();
                return sendError('Category cannot be its own parent', [], 422);
            }
            
            $category->update($request->only(['name', 'parent_id']));
            
            DB::commit();
            return sendResponse($category, 'Category updated successfully');
        } catch (Exception $e) {
            DB::rollBack();
            return errorLog("Failed to update category: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    public function deleteCategory($id)
    {
        try {
            DB::beginTransaction();
            
            $category = MockExamCategory::findOrFail($id);
            
            if ($category->children()->exists()) {
                DB::rollBack();
                return sendError('Cannot delete category with sub-categories', [], 400);
            }
            
            if ($category->mockExams()->exists()) {
                DB::rollBack();
                return sendError('Cannot delete category with associated mock exams', [], 400);
            }
            
            // Check for papers as well
            if ($category->papers()->exists()) {
                DB::rollBack();
                return sendError('Cannot delete category with associated papers', [], 400);
            }
            
            $category->delete();
            
            DB::commit();
            return sendResponse(null, 'Category deleted successfully');
        } catch (Exception $e) {
            DB::rollBack();
            return errorLog("Failed to delete category: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    public function index(Request $request)
    {
        try {
            $categoryId = $request->input('category_id');
            $format = $request->input('format');
            $schoolId = $request->input('school_id');

            $papers = Paper::with([
                'category:id,name,parent_id',
                'school:id,name',
                // TEMPORARILY DISABLED - Questions loading
                // 'questions:id,paper_id',
                'format:id,name',
                'media', // Eager load all media (images and PDFs)
            ])
                ->when($categoryId, fn($q) => $q->where('category_id', $categoryId))
                ->when($format, fn($q) => $q->where('format_id', $format))
                ->when($schoolId, fn($q) => $q->where('school_id', $schoolId))
                ->orderBy('created_at', 'desc')
                ->paginate(10)
                ->through(function ($paper) {
                    // Get all PDFs for this paper
                    $pdfs = $paper->getMedia('paper_pdfs')->map(function($media) {
                        return [
                            'id' => $media->id,
                            'name' => $media->name,
                            'file_name' => $media->file_name,
                            'size' => $media->size,
                            'size_human' => $media->human_readable_size,
                            'url' => $media->getUrl(),
                            'order' => $media->getCustomProperty('order', 0),
                            'original_name' => $media->getCustomProperty('original_name', $media->file_name),
                            'mime_type' => $media->mime_type,
                            'created_at' => $media->created_at?->toDateTimeString(),
                        ];
                    })->sortBy('order')->values();

                    return [
                        'id' => $paper->id,
                        'name' => $paper->name,
                        'description' => $paper->description,
                        'category' => $paper->category?->name,
                        'format' => $paper->format->name,
                        'price' =>  $paper->price,
                        'currency' => $paper->currency,
                        'duration_minutes' => $paper->duration_minutes,
                        'total_marks' => $paper->total_marks,
                        'school' => $paper->school?->name,
                        // TEMPORARILY DISABLED - Questions count
                        // 'questions_count' => $paper->questions->count(),
                        'pdfs_count' => $pdfs->count(),
                        'pdfs' => $pdfs, // Include complete PDF list
                        'status' => $paper->status,
                        'image' => $paper->getFirstMediaUrl('paper_image') ?: config('constants.dummy_image'),
                    ];
                });

            return sendResponse($papers, 'Papers fetched successfully');
        } catch (Exception $e) {
            return errorLog("Failed to fetch papers: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    public function show($id)
    {
        try {
            $paper = Paper::with([
                'category:id,name',
                'school:id,name',
                'questions.options.answer',
                'format:id,name',
            ])->find($id);
          
            if(empty($paper)){
                return sendError('Paper not found');
            }
            $data = [
                'id' => $paper->id,
                'name' => $paper->name,
                'description' => $paper->description,
                'category_id' => $paper->category_id,
                'category_name' => $paper->category?->name,
                'format_id' => $paper->format_id,
                'format' => $paper->format->name,
                'price' => $paper->price,
                'currency' => $paper->currency,
                'duration_minutes' => $paper->duration_minutes,
                'total_marks' => $paper->total_marks,
                'school_id' => $paper->school_id,
                'school_name' => $paper->school?->name,
                'image' => $paper->getFirstMediaUrl('paper_image') ?: config('constants.dummy_image'),
                
                // NEW: Complete PDF list
                'pdfs' => $paper->getMedia('paper_pdfs')->map(function($media) {
                    return [
                        'id' => $media->id,
                        'name' => $media->name,
                        'file_name' => $media->file_name,
                        'size' => $media->size,
                        'size_human' => $media->human_readable_size,
                        'url' => $media->getUrl(),
                        'download_url' => $media->getUrl(), // For download functionality
                        'order' => $media->getCustomProperty('order', 0),
                        'original_name' => $media->getCustomProperty('original_name', $media->file_name),
                        'mime_type' => $media->mime_type,
                        'created_at' => $media->created_at?->toDateTimeString(),
                    ];
                })->sortBy('order')->values(),
                
                // TEMPORARILY DISABLED - Questions data
                /* 
                'questions' => $paper->questions->map(function ($question) {
                    // Fix: Properly check if option has an answer relationship
                    $correctOption = $question->options->first(function($opt) {
                        return $opt->answer()->exists();
                    });
                    return [
                        'id' => $question->id,
                        'question_text' => $question->question_text,
                        'marks' => $question->marks,
                        'duration_in_sec' => $question->duration_in_sec,
                        'order' => $question->order,
                        'correct_answer' => $correctOption?->option_text,
                        'options' => $question->options->map(fn($opt) => [
                            'id' => $opt->id,
                            'option_text' => $opt->option_text,
                            'order' => $opt->order,
                        ]),
                    ];
                }),
                */
            ];

            return sendResponse($data, 'Paper fetched successfully');
        } catch (Exception $e) {
            return errorLog("Failed to fetch paper: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    public function store(StorePaperRequest $request)
    {
        try {
            DB::beginTransaction();

            // TEMPORARILY DISABLED - Marks calculation
            // $marks = $request->input('marks', []);
            // $totalMarks = !empty($marks) ? array_sum($marks) : count($request->questions);
            $totalMarks = null; // Temporarily set to null (column is nullable)
         
            // Generate unique slug (include soft-deleted records in check)
            $baseSlug = Str::slug($request->name);
            $slug = $baseSlug;
            $counter = 1;
            while (Paper::withTrashed()->where('slug', $slug)->exists()) {
                $slug = $baseSlug . '-' . $counter++;
            }

            $paper = Paper::create([
                'name' => $request->name,
                'description' => $request->description,
                'category_id' => $request->category_id,
                'format_id' => $request->format_id,
                'price' => $request->price,
                'currency' => $request->currency ?? '€',
                'duration_minutes' => $request->duration_minutes,
                'total_marks' => $totalMarks,
                'school_id' => $request->school_id ?? null,
                'slug' => $slug,
            ]);

            /* TEMPORARILY DISABLED - Questions/Options/Answers Logic
            foreach ($request->input('questions') as $key => $question) {
                $questionObj = PaperQuestion::create([
                    'paper_id' => $paper->id,
                    'question_text' => $question,
                    'marks' => $marks[$key] ?? 1,
                    'duration_in_sec' => $request->input('duration_in_sec')[$key],
                    'order' => $key + 1,
                ]);

                foreach ($request->input('options')[$key] as $option) {
                    $optionObj = PaperOption::create([
                        'paper_question_id' => $questionObj->id,
                        'option_text' => $option,
                    ]);

                    if ($optionObj->option_text == $request->input('answers')[$key]) {
                        // Use updateOrCreate to prevent duplicates
                        PaperAnswer::updateOrCreate(
                            ['paper_option_id' => $optionObj->id],
                            ['status' => config('constants.statuses.APPROVED')]
                        );
                    }
                }
            }
            END TEMPORARILY DISABLED SECTION */

            if ($request->hasFile('paper_image')) {
                try {
                    Log::info("Image file detected in create request, processing upload");
                    $file = $request->file('paper_image');
                    $tempPath = $file->store('temp/paper_images', 'local');
                    
                    if ($tempPath) {
                        Log::info("Temporary file stored", ['temp_path' => $tempPath]);
                        UploadPaperImageJob::dispatch(
                            $paper->id,
                            $tempPath,
                            $file->getClientOriginalName(),
                            $file->getMimeType()
                        );
                        Log::info("UploadPaperImageJob dispatched for paper ID: {$paper->id}");
                    }
                } catch (Exception $e) {
                    Log::error("Failed to process image upload", ['error' => $e->getMessage()]);
                }
            }

            // NEW: Handle multiple PDF uploads (max 10)
            if ($request->hasFile('paper_pdfs')) {
                try {
                    $pdfFiles = $request->file('paper_pdfs');
                    $pdfCount = count($pdfFiles);
                    
                    Log::info("PDF files detected in create request", [
                        'count' => $pdfCount,
                        'max_allowed' => 10
                    ]);
                    
                    // Double-check the count (validation should catch this, but be safe)
                    if ($pdfCount > 10) {
                        Log::warning("Attempted to upload more than 10 PDFs", ['count' => $pdfCount]);
                        throw new Exception('Maximum 10 PDF files allowed per paper.');
                    }
                    
                    foreach ($pdfFiles as $index => $pdfFile) {
                        Log::info("Processing PDF file", [
                            'index' => $index,
                            'original_name' => $pdfFile->getClientOriginalName(),
                            'size' => $pdfFile->getSize(),
                            'mime_type' => $pdfFile->getMimeType()
                        ]);
                        
                        // Store PDF directly using Spatie Media Library (no temp storage needed)
                        $media = $paper->addMedia($pdfFile)
                            ->usingName(pathinfo($pdfFile->getClientOriginalName(), PATHINFO_FILENAME))
                            ->usingFileName($pdfFile->getClientOriginalName())
                            ->withCustomProperties([
                                'order' => $index,
                                'original_name' => $pdfFile->getClientOriginalName()
                            ])
                            ->toMediaCollection('paper_pdfs');
                        
                        Log::info("PDF file uploaded successfully", [
                            'index' => $index,
                            'media_id' => $media->id,
                            'file_name' => $media->file_name
                        ]);
                    }
                    
                    Log::info("All PDF files uploaded successfully", [
                        'paper_id' => $paper->id,
                        'total_pdfs' => $pdfCount
                    ]);
                } catch (Exception $e) {
                    Log::error("Failed to process PDF uploads", [
                        'error' => $e->getMessage(),
                        'paper_id' => $paper->id,
                        'trace' => $e->getTraceAsString()
                    ]);
                    // Re-throw to rollback transaction
                    throw $e;
                }
            }

            PaperPrice::dispatch($paper->id);
            DB::commit();

            return sendResponse(['paper_id' => $paper->id], 'Paper created successfully', 201);
        } catch (Exception $e) {
            DB::rollBack();
            return errorLog("Failed to create paper: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    public function update(UpdatePaperRequest $request, $id)
    {
        try {
            Log::info("=== PAPER UPDATE STARTED ===", [
                'paper_id' => $id,
                'method' => $request->method(),
                'has_file' => $request->hasFile('paper_image'),
                'has_pdfs' => $request->hasFile('paper_pdfs'),
                'content_type' => $request->header('Content-Type')
            ]);

            $paper = Paper::findOrFail($id);
            
            // Check authorization using policy
            $this->authorize('update', $paper);
            
            DB::beginTransaction();

            $oldPrice = $paper->price;

            $updateData = [];
            if ($request->has('name')) {
                $updateData['name'] = $request->name;
                
                // Generate unique slug when name changes (include soft-deleted records in check)
                $baseSlug = Str::slug($request->name);
                $slug = $baseSlug;
                $counter = 1;
                // Check if slug exists for other papers (exclude current paper, include soft-deleted)
                while (Paper::withTrashed()->where('slug', $slug)->where('id', '!=', $paper->id)->exists()) {
                    $slug = $baseSlug . '-' . $counter++;
                }
                $updateData['slug'] = $slug;
            }
            if ($request->has('description')) $updateData['description'] = $request->description;
            if ($request->has('category_id')) $updateData['category_id'] = $request->category_id;
            if ($request->has('format_id')) $updateData['format_id'] = $request->format_id;
            if ($request->has('price')) $updateData['price'] = $request->price;
            if ($request->has('currency')) $updateData['currency'] = $request->currency;
            if ($request->has('duration_minutes')) $updateData['duration_minutes'] = $request->duration_minutes;
            if ($request->has('school_id')) $updateData['school_id'] = $request->school_id;

            if (!empty($updateData)) {
                $paper->update($updateData);
            }

            /* TEMPORARILY DISABLED - Questions/Options/Answers Update Logic
            // Handle questions/options/answers update if provided
            if ($request->has('questions') && $request->has('options') && $request->has('answers')) {
                Log::info("Updating questions/options/answers for paper ID: {$id}");

                // Delete existing questions (cascade will delete options and answers)
                $paper->questions()->delete();

                $marks = $request->input('marks', []);
                $totalMarks = !empty($marks) ? array_sum($marks) : count($request->questions);
                
                // Update total_marks if questions are being updated
                $paper->update(['total_marks' => $totalMarks]);

                // Create new questions, options, and answers
                foreach ($request->input('questions') as $key => $question) {
                    $questionObj = PaperQuestion::create([
                        'paper_id' => $paper->id,
                        'question_text' => $question,
                        'marks' => $marks[$key] ?? 1,
                        'duration_in_sec' => $request->input('duration_in_sec')[$key],
                        'order' => $key + 1,
                    ]);

                    foreach ($request->input('options')[$key] as $option) {
                        $optionObj = PaperOption::create([
                            'paper_question_id' => $questionObj->id,
                            'option_text' => $option,
                        ]);

                        if ($optionObj->option_text == $request->input('answers')[$key]) {
                            PaperAnswer::updateOrCreate(
                                ['paper_option_id' => $optionObj->id],
                                ['status' => config('constants.statuses.APPROVED')]
                            );
                        }
                    }
                }

                Log::info("Questions/options/answers updated successfully for paper ID: {$id}");
            }
            END TEMPORARILY DISABLED SECTION */

            // Handle paper_image: upload new or delete existing
            if ($request->hasFile('paper_image')) {
                // New image uploaded - replace existing
                try {
                    Log::info("Image file detected in update request, processing upload");
                    
                    $paper->clearMediaCollection('paper_image');
                    
                    $file = $request->file('paper_image');
                    $tempPath = $file->store('temp/paper_images', 'local');
                    
                    if ($tempPath) {
                        Log::info("Temporary file stored", ['temp_path' => $tempPath]);
                        UploadPaperImageJob::dispatch(
                            $paper->id,
                            $tempPath,
                            $file->getClientOriginalName(),
                            $file->getMimeType()
                        );
                        Log::info("UploadPaperImageJob dispatched for paper ID: {$paper->id}");
                    }
                } catch (Exception $e) {
                    Log::error("Failed to process image upload in update", ['error' => $e->getMessage()]);
                }
            } elseif ($request->has('paper_image')) {
                // paper_image field present but no file uploaded - delete existing image
                // This handles the case where frontend sends empty file field to delete image
                try {
                    $paperImageValue = $request->input('paper_image');
                    // Check if it's empty string or null (multipart form data sends empty file as empty string)
                    if (empty($paperImageValue) || $paperImageValue === '') {
                        Log::info("Empty paper_image field detected, deleting existing image", [
                            'paper_id' => $paper->id,
                            'value' => $paperImageValue
                        ]);
                        $paper->clearMediaCollection('paper_image');
                        Log::info("Paper image deleted successfully", ['paper_id' => $paper->id]);
                    }
                } catch (Exception $e) {
                    Log::error("Failed to delete paper image", [
                        'error' => $e->getMessage(),
                        'paper_id' => $paper->id
                    ]);
                }
            }

            // Handle PDF deletion: delete specified PDFs by media ID
            if ($request->has('deleted_pdf_ids') && is_array($request->input('deleted_pdf_ids'))) {
                try {
                    $deletedPdfIds = $request->input('deleted_pdf_ids');
                    Log::info("PDF deletion requested", [
                        'paper_id' => $paper->id,
                        'deleted_pdf_ids' => $deletedPdfIds
                    ]);
                    
                    // Get all PDFs for this paper
                    $pdfs = $paper->getMedia('paper_pdfs');
                    
                    foreach ($deletedPdfIds as $mediaId) {
                        $media = $pdfs->where('id', $mediaId)->first();
                        
                        if ($media) {
                            $media->delete();
                            Log::info("PDF deleted successfully", [
                                'paper_id' => $paper->id,
                                'media_id' => $mediaId,
                                'file_name' => $media->file_name
                            ]);
                        } else {
                            Log::warning("PDF not found for deletion", [
                                'paper_id' => $paper->id,
                                'media_id' => $mediaId
                            ]);
                        }
                    }
                } catch (Exception $e) {
                    Log::error("Failed to delete PDFs", [
                        'error' => $e->getMessage(),
                        'paper_id' => $paper->id
                    ]);
                    // Don't throw - allow update to continue
                }
            }

            // NEW: Handle multiple PDF uploads (max 10)
            if ($request->hasFile('paper_pdfs')) {
                try {
                    $pdfFiles = $request->file('paper_pdfs');
                    $pdfCount = count($pdfFiles);
                    
                    Log::info("PDF files detected in update request", [
                        'count' => $pdfCount,
                        'max_allowed' => 10,
                        'paper_id' => $paper->id
                    ]);
                    
                    // Double-check the count (validation should catch this, but be safe)
                    if ($pdfCount > 10) {
                        Log::warning("Attempted to upload more than 10 PDFs in update", ['count' => $pdfCount]);
                        throw new Exception('Maximum 10 PDF files allowed per paper.');
                    }
                    
                    // Get existing PDF count to ensure we don't exceed 10 total
                    $existingPdfCount = $paper->getMedia('paper_pdfs')->count();
                    if (($existingPdfCount + $pdfCount) > 10) {
                        throw new Exception('Total PDF files cannot exceed 10. Please delete some PDFs first.');
                    }
                    
                    foreach ($pdfFiles as $index => $pdfFile) {
                        Log::info("Processing PDF file in update", [
                            'index' => $index,
                            'original_name' => $pdfFile->getClientOriginalName(),
                            'size' => $pdfFile->getSize(),
                            'mime_type' => $pdfFile->getMimeType()
                        ]);
                        
                        // Store PDF directly using Spatie Media Library (no temp storage needed)
                        $media = $paper->addMedia($pdfFile)
                            ->usingName(pathinfo($pdfFile->getClientOriginalName(), PATHINFO_FILENAME))
                            ->usingFileName($pdfFile->getClientOriginalName())
                            ->withCustomProperties([
                                'order' => $existingPdfCount + $index, // Maintain order after existing PDFs
                                'original_name' => $pdfFile->getClientOriginalName()
                            ])
                            ->toMediaCollection('paper_pdfs');
                        
                        Log::info("PDF file uploaded successfully in update", [
                            'index' => $index,
                            'media_id' => $media->id,
                            'file_name' => $media->file_name
                        ]);
                    }
                    
                    Log::info("All PDF files uploaded successfully in update", [
                        'paper_id' => $paper->id,
                        'total_pdfs' => $pdfCount
                    ]);
                } catch (Exception $e) {
                    Log::error("Failed to process PDF uploads in update", [
                        'error' => $e->getMessage(),
                        'paper_id' => $paper->id,
                        'trace' => $e->getTraceAsString()
                    ]);
                    // Re-throw to rollback transaction
                    throw $e;
                }
            }

            DB::commit();

            if ($request->has('price') && abs($oldPrice - $request->price) > 0.01) {
                Log::info("Price changed, dispatching UpdatePaperStripePrice job");
                UpdatePaperStripePrice::dispatch($paper->id);
            }

            return sendResponse($paper, 'Paper updated successfully');
        } catch (Exception $e) {
            DB::rollBack();
            return errorLog("Failed to update paper: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    public function destroy($id)
    {
        try {
            DB::beginTransaction();
            
            $paper = Paper::findOrFail($id);
            
            // Check including soft-deleted purchases
            if (PaperPurchase::withTrashed()->where('paper_id', $id)->exists()) {
                DB::rollBack();
                return sendError('Cannot delete paper. This paper has been purchased by one or more parents or students.', [], 400);
            }

            $paper->delete();

            DB::commit();
            return sendResponse(null, 'Paper deleted successfully');
        } catch (Exception $e) {
            DB::rollBack();
            return errorLog("Failed to delete paper: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    public function toggleStatus(TogglePaperStatusRequest $request, $id)
    {
        try {
            $paper = Paper::findOrFail($id);
            
            $status = $request->action === 'activate' 
                ? config('constants.statuses.APPROVED', 2) 
                : config('constants.statuses.REJECTED', 3);
                
            $paper->update(['status' => $status]);
            
            return sendResponse([
                'id' => $paper->id,
                'status' => $status,
                'status_label' => $request->action === 'activate' ? 'approved' : 'rejected'
            ], 'Paper status updated successfully');
        } catch (Exception $e) {
            return errorLog("Failed to update paper status: {$e->getMessage()}");
        }
    }

    public function myPapers()
    {
        try {
            $userId = Auth::id();

            $purchases = PaperPurchase::with('paper:id,name,duration_minutes,total_marks')
                ->where('user_id', $userId)
                ->orderBy('purchased_at', 'desc')
                ->get()
                ->map(function ($purchase) {
                    return [
                        'purchase_id' => $purchase->id,
                        'paper_id' => $purchase->paper_id,
                        'paper_name' => $purchase->paper->name,
                        'duration_minutes' => $purchase->paper->duration_minutes,
                        'total_marks' => $purchase->total_marks,
                        'score' => $purchase->score,
                        'status' => $purchase->status,
                        'purchased_at' => $purchase->purchased_at,
                        'started_at' => $purchase->started_at,
                        'completed_at' => $purchase->completed_at,
                    ];
                });

            return sendResponse($purchases, 'My papers fetched successfully');
        } catch (Exception $e) {
            return errorLog("Failed to fetch user papers: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    public function startPaper($paperId)
    {
        try {
            $userId = Auth::id();

            $purchase = PaperPurchase::where('user_id', $userId)
                ->where('paper_id', $paperId)
                ->first();

            if (!$purchase) {
                return sendError('You have not purchased this paper', [], 403);
            }

            // Add payment status check
            if ($purchase->payment_status !== config('constants.stripe_payment_status.PAID')) {
                return sendError('Payment not completed', [], 400);
            }

            // Add check for already started
            if ($purchase->started_at !== null) {
                return sendError('Paper has already been started', [], 400);
            }

            if ($purchase->status == config('constants.mock_exam_purchase_status.COMPLETED')) {
                return sendError('You have already completed this paper', [], 400);
            }

            $purchase->update([
                'started_at' => now(),
                'status' => config('constants.mock_exam_purchase_status.IN_PROGRESS'),
            ]);

            return sendResponse(['purchase_id' => $purchase->id], 'Paper started successfully');
        } catch (Exception $e) {
            return errorLog("Failed to start paper: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    public function submitPaper(Request $request, $purchaseId)
    {
        $request->validate([
            'answers' => 'required|array',
            'answers.*.question_id' => 'required|exists:paper_questions,id',
            'answers.*.option_id' => 'required|exists:paper_options,id',
        ]);

        try {
            DB::beginTransaction();

            $purchase = PaperPurchase::with('paper.questions')->findOrFail($purchaseId);

            if ($purchase->user_id !== Auth::id()) {
                DB::rollBack();
                return sendError('Unauthorized', [], 403);
            }

            // Add validation: Paper must be started
            if (!$purchase->started_at) {
                DB::rollBack();
                return sendError('Paper has not been started', [], 400);
            }

            // Add validation: Paper must not be completed
            if ($purchase->status == config('constants.mock_exam_purchase_status.COMPLETED')) {
                DB::rollBack();
                return sendError('Paper has already been completed', [], 400);
            }

            // Validate all questions belong to the paper
            $paperQuestionIds = $purchase->paper->questions->pluck('id')->toArray();
            $submittedQuestionIds = array_column($request->answers, 'question_id');
            
            if (count($submittedQuestionIds) !== count($paperQuestionIds) ||
                !empty(array_diff($submittedQuestionIds, $paperQuestionIds))) {
                DB::rollBack();
                return sendError('Invalid questions submitted', [], 400);
            }

            // Validate each option belongs to its question
            foreach ($request->answers as $answer) {
                $option = PaperOption::where('id', $answer['option_id'])
                    ->where('paper_question_id', $answer['question_id'])
                    ->first();
                
                if (!$option) {
                    DB::rollBack();
                    return sendError("Option does not belong to question {$answer['question_id']}", [], 400);
                }
            }

            $score = 0;
            $totalMarks = 0;

            foreach ($request->answers as $answer) {
                $question = PaperQuestion::find($answer['question_id']);
                $option = PaperOption::find($answer['option_id']);
                
                // Fix: Properly check if option has an answer relationship
                $isCorrect = PaperAnswer::where('paper_option_id', $option->id)->exists();

                if ($isCorrect) {
                    $score += $question->marks;
                }
                $totalMarks += $question->marks;

                PaperUserAnswer::create([
                    'paper_purchase_id' => $purchaseId,
                    'paper_question_id' => $answer['question_id'],
                    'paper_option_id' => $answer['option_id'],
                    'is_correct' => $isCorrect,
                ]);
            }

            $purchase->update([
                'completed_at' => now(),
                'score' => $score,
                'total_marks' => $totalMarks,
                'status' => config('constants.mock_exam_purchase_status.COMPLETED'),
            ]);

            DB::commit();

            return sendResponse([
                'score' => $score,
                'total_marks' => $totalMarks,
                'percentage' => $totalMarks > 0 ? round(($score / $totalMarks) * 100, 2) : 0,
            ], 'Paper submitted successfully');
        } catch (Exception $e) {
            DB::rollBack();
            return errorLog("Failed to submit paper: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }
}

