<?php

namespace App\Http\Controllers\Api\Admin\Assign;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\StoreMockExamRequest;
use App\Http\Requests\Api\Admin\UpdateMockExamRequest;
use App\Http\Requests\Api\Admin\ToggleMockExamStatusRequest;
use App\Jobs\MockExamPrice;
use App\Jobs\UploadMockExamImageJob;
use App\Jobs\UpdateMockExamStripePrice;
use App\Models\MockExam;
use App\Models\MockExamAnswer;
use App\Models\MockExamCategory;
use App\Models\MockExamOption;
use App\Models\MockExamPurchase;
use App\Models\MockExamQuestion;
use App\Models\MockExamUserAnswer;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MockExamController extends Controller
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
            $category = MockExamCategory::findOrFail($id);
            if ($request->has('parent_id') && $request->parent_id == $category->id) {
                return sendError('Category cannot be its own parent', [], 422);
            }
            
            $category->update($request->only(['name', 'parent_id']));
            
            return sendResponse($category, 'Category updated successfully');
        } catch (Exception $e) {
            return errorLog("Failed to update category: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    public function deleteCategory($id)
    {
        try {
            $category = MockExamCategory::findOrFail($id);
            
            if ($category->children()->exists()) {
                return sendError('Cannot delete category with sub-categories', [], 400);
            }
            
            if ($category->mockExams()->exists()) {
                return sendError('Cannot delete category with associated mock exams', [], 400);
            }
            
            $category->delete();
            
            return sendResponse(null, 'Category deleted successfully');
        } catch (Exception $e) {
            return errorLog("Failed to delete category: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    public function index(Request $request)
    {
        try {
            $categoryId = $request->input('category_id');
            $format = $request->input('format');
            $schoolId = $request->input('school_id');

            $mockExams = MockExam::with([
                'category:id,name,parent_id',
                'school:id,name',
                'questions:id,mock_exam_id',
                'format:id,name',
            ])
                ->when($categoryId, fn($q) => $q->where('category_id', $categoryId))
                ->when($format, fn($q) => $q->where('format_id', $format))
                ->when($schoolId, fn($q) => $q->where('school_id', $schoolId))
                ->orderBy('created_at', 'desc')
                ->paginate(10)
                ->through(function ($exam) {
                    return [
                        'id' => $exam->id,
                        'name' => $exam->name,
                        'description' => $exam->description,
                        'category' => $exam->category?->name,
                        'format' => $exam->format->name,
                        'price' =>  $exam->price,
                        'currency' => $exam->currency,
                        'duration_minutes' => $exam->duration_minutes,
                        'total_marks' => $exam->total_marks,
                        'school' => $exam->school?->name,
                        'questions_count' => $exam->questions->count(),
                        'status' => $exam->status,
                        'image' => $exam->getFirstMediaUrl('mock_exam_image') ?: config('constants.dummy_image'),
                    ];
                });

            return sendResponse($mockExams, 'Mock exams fetched successfully');
        } catch (Exception $e) {
            return errorLog("Failed to fetch mock exams: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    public function show($id)
    {
        try {
            $mockExam = MockExam::with([
                'category:id,name',
                'school:id,name',
                'questions.options.answer',
                'format:id,name',
            ])->find($id);
          
            if(empty($mockExam)){
                return sendError('Mock exam not found');
            }
            $data = [
                'id' => $mockExam->id,
                'name' => $mockExam->name,
                'description' => $mockExam->description,
                'category_id' => $mockExam->category_id,
                'category_name' => $mockExam->category?->name,
                'format_id' => $mockExam->format_id,
                'format' => $mockExam->format->name,
                'price' => $mockExam->price,
                'currency' => $mockExam->currency,
                'duration_minutes' => $mockExam->duration_minutes,
                'total_marks' => $mockExam->total_marks,
                'school_id' => $mockExam->school_id,
                'school_name' => $mockExam->school?->name,
                'image' => $mockExam->getFirstMediaUrl('mock_exam_image') ?: config('constants.dummy_image'),
                'questions' => $mockExam->questions->map(function ($question) {
                    $correctOption = $question->options->first(fn($opt) => $opt->answer !== null);
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
            ];

            return sendResponse($data, 'Mock exam fetched successfully');
        } catch (Exception $e) {
            return errorLog("Failed to fetch mock exam: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    public function store(StoreMockExamRequest $request)
    {
        try {
            DB::beginTransaction();

            $marks = $request->input('marks', []);
            $totalMarks = !empty($marks) ? array_sum($marks) : count($request->questions);
         
            $mockExam = MockExam::create([
                'name' => $request->name,
                'description' => $request->description,
                'category_id' => $request->category_id,
                'format_id' => $request->format_id,
                'price' => $request->price,
                'currency' => $request->currency ?? '€',
                'duration_minutes' => $request->duration_minutes,
                'total_marks' => $totalMarks,
                'school_id' => $request->school_id ?? null,
                'slug' => Str::slug($request->name),
            ]);

            foreach ($request->input('questions') as $key => $question) {
                $questionObj = MockExamQuestion::firstOrCreate([
                    'mock_exam_id' => $mockExam->id,
                    'question_text' => $question,
                    'marks' => $marks[$key] ?? 1,
                    'duration_in_sec' => $request->input('duration_in_sec')[$key],
                    'order' => $key + 1,
                ]);

                foreach ($request->input('options')[$key] as $option) {
                    $optionObj = MockExamOption::firstOrCreate([
                        'mock_exam_question_id' => $questionObj->id,
                        'option_text' => $option,
                    ]);

                    if ($optionObj->option_text == $request->input('answers')[$key]) {
                        MockExamAnswer::firstOrCreate([
                            'mock_exam_option_id' => $optionObj->id,
                        ]);
                    }
                }
            }

            if ($request->hasFile('mock_exam_image')) {
                try {
                    Log::info("Image file detected in create request, processing upload");
                    $file = $request->file('mock_exam_image');
                    $tempPath = $file->store('temp/mock_exam_images', 'local');
                    
                    if ($tempPath) {
                        Log::info("Temporary file stored", ['temp_path' => $tempPath]);
                        UploadMockExamImageJob::dispatch(
                            $mockExam->id,
                            $tempPath,
                            $file->getClientOriginalName(),
                            $file->getMimeType()
                        );
                        Log::info("UploadMockExamImageJob dispatched for mock exam ID: {$mockExam->id}");
                    }
                } catch (Exception $e) {
                    Log::error("Failed to process image upload", ['error' => $e->getMessage()]);
                }
            }

            MockExamPrice::dispatch($mockExam->id);
            DB::commit();

            return sendResponse(['mock_exam_id' => $mockExam->id], 'Mock exam created successfully', 201);
        } catch (Exception $e) {
            DB::rollBack();
            return errorLog("Failed to create mock exam: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    public function update(UpdateMockExamRequest $request, $id)
    {
        try {
            Log::info("=== MOCK EXAM UPDATE STARTED ===", [
                'mock_exam_id' => $id,
                'method' => $request->method(),
                'has_file' => $request->hasFile('mock_exam_image'),
                'content_type' => $request->header('Content-Type')
            ]);

            DB::beginTransaction();

            $mockExam = MockExam::findOrFail($id);
            $oldPrice = $mockExam->price;

            $updateData = [];
            if ($request->has('name')) $updateData['name'] = $request->name;
            if ($request->has('description')) $updateData['description'] = $request->description;
            if ($request->has('category_id')) $updateData['category_id'] = $request->category_id;
            if ($request->has('format_id')) $updateData['format_id'] = $request->format_id;
            if ($request->has('price')) $updateData['price'] = $request->price;
            if ($request->has('currency')) $updateData['currency'] = $request->currency;
            if ($request->has('duration_minutes')) $updateData['duration_minutes'] = $request->duration_minutes;
            if ($request->has('school_id')) $updateData['school_id'] = $request->school_id;

            if (!empty($updateData)) {
                $mockExam->update($updateData);
            }

            if ($request->hasFile('mock_exam_image')) {
                try {
                    Log::info("Image file detected in update request, processing upload");
                    
                    $mockExam->clearMediaCollection('mock_exam_image');
                    
                    $file = $request->file('mock_exam_image');
                    $tempPath = $file->store('temp/mock_exam_images', 'local');
                    
                    if ($tempPath) {
                        Log::info("Temporary file stored", ['temp_path' => $tempPath]);
                        UploadMockExamImageJob::dispatch(
                            $mockExam->id,
                            $tempPath,
                            $file->getClientOriginalName(),
                            $file->getMimeType()
                        );
                        Log::info("UploadMockExamImageJob dispatched for mock exam ID: {$mockExam->id}");
                    }
                } catch (Exception $e) {
                    Log::error("Failed to process image upload in update", ['error' => $e->getMessage()]);
                }
            }

            DB::commit();

            if ($request->has('price') && abs($oldPrice - $request->price) > 0.01) {
                Log::info("Price changed, dispatching UpdateMockExamStripePrice job");
                UpdateMockExamStripePrice::dispatch($mockExam->id);
            }

            return sendResponse($mockExam, 'Mock exam updated successfully');
        } catch (Exception $e) {
            DB::rollBack();
            return errorLog("Failed to update mock exam: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    public function destroy($id)
    {
        try {
            $mockExam = MockExam::findOrFail($id);
            
            if (MockExamPurchase::where('mock_exam_id', $id)->exists()) {
                return sendError('Cannot delete mock exam. This mock exam has been purchased by one or more parents or students.', [], 400);
            }

            $mockExam->delete();

            return sendResponse(null, 'Mock exam deleted successfully');
        } catch (Exception $e) {
            return errorLog("Failed to delete mock exam: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    public function toggleStatus(ToggleMockExamStatusRequest $request, $id)
    {
        try {
            $mockExam = MockExam::findOrFail($id);
            
            $status = $request->action === 'activate' 
                ? config('constants.statuses.APPROVED', 2) 
                : config('constants.statuses.REJECTED', 3);
                
            $mockExam->update(['status' => $status]);
            
            return sendResponse([
                'id' => $mockExam->id,
                'status' => $status,
                'status_label' => $request->action === 'activate' ? 'approved' : 'rejected'
            ], 'Mock exam status updated successfully');
        } catch (Exception $e) {
            return errorLog("Failed to update mock exam status: {$e->getMessage()}");
        }
    }

    public function myMockExams()
    {
        try {
            $userId = Auth::id();

            $purchases = MockExamPurchase::with('mockExam:id,name,duration_minutes,total_marks')
                ->where('user_id', $userId)
                ->orderBy('purchased_at', 'desc')
                ->get()
                ->map(function ($purchase) {
                    return [
                        'purchase_id' => $purchase->id,
                        'mock_exam_id' => $purchase->mock_exam_id,
                        'exam_name' => $purchase->mockExam->name,
                        'duration_minutes' => $purchase->mockExam->duration_minutes,
                        'total_marks' => $purchase->total_marks,
                        'score' => $purchase->score,
                        'status' => $purchase->status,
                        'purchased_at' => $purchase->purchased_at,
                        'started_at' => $purchase->started_at,
                        'completed_at' => $purchase->completed_at,
                    ];
                });

            return sendResponse($purchases, 'My mock exams fetched successfully');
        } catch (Exception $e) {
            return errorLog("Failed to fetch user mock exams: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    public function startExam($mockExamId)
    {
        try {
            $userId = Auth::id();

            $purchase = MockExamPurchase::where('user_id', $userId)
                ->where('mock_exam_id', $mockExamId)
                ->first();

            if (!$purchase) {
                return sendError('You have not purchased this mock exam', [], 403);
            }

            if ($purchase->status == config('constants.mock_exam_purchase_status.COMPLETED')) {
                return sendError('You have already completed this exam', [], 400);
            }

            $purchase->update([
                'started_at' => now(),
                'status' => config('constants.mock_exam_purchase_status.IN_PROGRESS'),
            ]);

            return sendResponse(['purchase_id' => $purchase->id], 'Exam started successfully');
        } catch (Exception $e) {
            return errorLog("Failed to start exam: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    public function submitExam(Request $request, $purchaseId)
    {
        $request->validate([
            'answers' => 'required|array',
            'answers.*.question_id' => 'required|exists:mock_exam_questions,id',
            'answers.*.option_id' => 'required|exists:mock_exam_options,id',
        ]);

        try {
            DB::beginTransaction();

            $purchase = MockExamPurchase::findOrFail($purchaseId);

            if ($purchase->user_id !== Auth::id()) {
                return sendError('Unauthorized', [], 403);
            }

            $score = 0;
            $totalMarks = 0;

            foreach ($request->answers as $answer) {
                $question = MockExamQuestion::find($answer['question_id']);
                $option = MockExamOption::find($answer['option_id']);
                $isCorrect = $option->answer !== null;

                if ($isCorrect) {
                    $score += $question->marks;
                }
                $totalMarks += $question->marks;

                MockExamUserAnswer::create([
                    'mock_exam_purchase_id' => $purchaseId,
                    'mock_exam_question_id' => $answer['question_id'],
                    'mock_exam_option_id' => $answer['option_id'],
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
                'percentage' => round(($score / $totalMarks) * 100, 2),
            ], 'Exam submitted successfully');
        } catch (Exception $e) {
            DB::rollBack();
            return errorLog("Failed to submit exam: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }
}
