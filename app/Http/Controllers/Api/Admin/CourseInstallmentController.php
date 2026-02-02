<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseInstallment;
use App\Models\CourseInstallmentItem;
use App\Models\CoursePrice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CourseInstallmentController extends Controller
{
    /**
     * Get installments for a course
     */
    public function index(Request $request, $courseId)
    {
        try {
            $course = Course::findOrFail($courseId);

            $installments = CourseInstallment::with(['installmentItems', 'coursePrice'])
                ->where('course_id', $courseId)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $installments,
                'message' => 'Installments retrieved successfully',
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to get installments: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve installments',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create installment plan for a course
     */
    public function store(Request $request, $courseId)
    {
        $validator = Validator::make($request->all(), [
            'course_price_id' => 'required|exists:course_prices,id',
            'number_of_installments' => 'required|integer|min:1|max:12',
            'total_course_fee' => 'required|numeric|min:0',
            'currency' => 'nullable|string|max:10',
            'installments' => 'required|array|min:1',
            'installments.*.installment_number' => 'required|integer|min:1',
            'installments.*.amount' => 'required|numeric|min:0',
            'installments.*.due_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $course = Course::findOrFail($courseId);
            $coursePrice = CoursePrice::findOrFail($request->course_price_id);

            // Verify course_price belongs to course
            if ($coursePrice->course_id != $courseId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Course price does not belong to this course',
                ], 400);
            }

            // Validate total amount matches sum of installments
            $totalInstallmentAmount = collect($request->installments)->sum('amount');
            if (abs($totalInstallmentAmount - $request->total_course_fee) > 0.01) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sum of installment amounts must equal total course fee',
                    'total_installment_amount' => $totalInstallmentAmount,
                    'total_course_fee' => $request->total_course_fee,
                ], 422);
            }

            // Validate installment numbers are sequential and unique
            $installmentNumbers = collect($request->installments)->pluck('installment_number')->sort()->values();
            $expectedNumbers = range(1, count($request->installments));
            if ($installmentNumbers->toArray() !== $expectedNumbers) {
                return response()->json([
                    'success' => false,
                    'message' => 'Installment numbers must be sequential starting from 1',
                ], 422);
            }

            DB::beginTransaction();

            // Deactivate existing installments
            CourseInstallment::where('course_id', $courseId)
                ->where('course_price_id', $request->course_price_id)
                ->update(['is_active' => false]);

            // Create new installment plan
            $installment = CourseInstallment::create([
                'course_id' => $courseId,
                'course_price_id' => $request->course_price_id,
                'number_of_installments' => $request->number_of_installments,
                'total_course_fee' => $request->total_course_fee,
                'currency' => $request->currency ?? 'gbp',
                'is_active' => true,
            ]);

            // Create installment items
            foreach ($request->installments as $item) {
                CourseInstallmentItem::create([
                    'course_installment_id' => $installment->id,
                    'installment_number' => $item['installment_number'],
                    'amount' => $item['amount'],
                    'due_date' => $item['due_date'] ?? null,
                ]);
            }

            DB::commit();

            $installment->load('installmentItems', 'coursePrice');

            return response()->json([
                'success' => true,
                'data' => $installment,
                'message' => 'Installment plan created successfully',
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to create installment plan: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'message' => 'Failed to create installment plan',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update installment plan
     */
    public function update(Request $request, $courseId, $installmentId)
    {
        $validator = Validator::make($request->all(), [
            'number_of_installments' => 'sometimes|integer|min:1|max:12',
            'total_course_fee' => 'sometimes|numeric|min:0',
            'currency' => 'nullable|string|max:10',
            'is_active' => 'sometimes|boolean',
            'installments' => 'sometimes|array|min:1',
            'installments.*.installment_number' => 'required|integer|min:1',
            'installments.*.amount' => 'required|numeric|min:0',
            'installments.*.due_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $installment = CourseInstallment::where('course_id', $courseId)
                ->where('id', $installmentId)
                ->firstOrFail();

            DB::beginTransaction();

            // Update installment plan
            if ($request->has('number_of_installments')) {
                $installment->number_of_installments = $request->number_of_installments;
            }
            if ($request->has('total_course_fee')) {
                $installment->total_course_fee = $request->total_course_fee;
            }
            if ($request->has('currency')) {
                $installment->currency = $request->currency;
            }
            if ($request->has('is_active')) {
                $installment->is_active = $request->is_active;
            }
            $installment->save();

            // Update installment items if provided
            if ($request->has('installments')) {
                // Validate total amount matches sum of installments
                $totalInstallmentAmount = collect($request->installments)->sum('amount');
                if (abs($totalInstallmentAmount - $installment->total_course_fee) > 0.01) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Sum of installment amounts must equal total course fee',
                    ], 422);
                }

                // Delete existing items
                $installment->installmentItems()->delete();

                // Create new items
                foreach ($request->installments as $item) {
                    CourseInstallmentItem::create([
                        'course_installment_id' => $installment->id,
                        'installment_number' => $item['installment_number'],
                        'amount' => $item['amount'],
                        'due_date' => $item['due_date'] ?? null,
                    ]);
                }
            }

            DB::commit();

            $installment->load('installmentItems', 'coursePrice');

            return response()->json([
                'success' => true,
                'data' => $installment,
                'message' => 'Installment plan updated successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to update installment plan: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'message' => 'Failed to update installment plan',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get specific installment plan
     */
    public function show($courseId, $installmentId)
    {
        try {
            $installment = CourseInstallment::with(['installmentItems', 'coursePrice'])
                ->where('course_id', $courseId)
                ->where('id', $installmentId)
                ->firstOrFail();

            return response()->json([
                'success' => true,
                'data' => $installment,
                'message' => 'Installment plan retrieved successfully',
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to get installment: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'message' => 'Installment plan not found',
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Delete installment plan
     */
    public function destroy($courseId, $installmentId)
    {
        try {
            $installment = CourseInstallment::where('course_id', $courseId)
                ->where('id', $installmentId)
                ->firstOrFail();

            // Check if there are any payments made
            $hasPayments = $installment->installmentPayments()->exists();
            if ($hasPayments) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete installment plan with existing payments',
                ], 400);
            }

            $installment->delete();

            return response()->json([
                'success' => true,
                'message' => 'Installment plan deleted successfully',
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to delete installment: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete installment plan',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
