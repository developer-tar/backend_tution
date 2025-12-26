<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Award;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Pagination\LengthAwarePaginator;

class AwardController extends Controller
{
    /**
     * Display a listing of awards.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        // Check if awards table exists
        if (!Schema::hasTable('awards')) {
            return response()->json([
                'success' => false,
                'message' => 'Database table not found',
                'error' => 'The awards table does not exist. Please run the migration: php artisan migrate',
            ], 500);
        }

        try {
            $search = $request->input('search');
            $status = $request->input('status');
            $type = $request->input('type');
            $perPage = $request->input('per_page', 10);

            $awards = Award::withCount('certificates')
                ->when($search, function ($query) use ($search) {
                    $query->where(function ($q) use ($search) {
                        $q->where('name', 'LIKE', "%{$search}%")
                            ->orWhere('description', 'LIKE', "%{$search}%");
                    });
                })
                ->when($status !== null, function ($query) use ($status) {
                    $query->where('status', $status);
                })
                ->when($type, function ($query) use ($type) {
                    $query->where('type', $type);
                })
                ->latest()
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Awards fetched successfully.',
                'data' => $awards,
            ], 200);
        } catch (\Exception $e) {
            return errorLog("Failed to fetch awards: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    /**
     * Store a newly created award.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        // Check if awards table exists
        if (!Schema::hasTable('awards')) {
            return response()->json([
                'success' => false,
                'message' => 'Database table not found',
                'error' => 'The awards table does not exist. Please run the migration: php artisan migrate',
            ], 500);
        }

        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'type' => 'nullable|string|max:255',
                'criteria' => 'nullable|string',
                'certificate_template' => 'nullable|string|max:255',
                'status' => 'nullable|integer|in:0,1',
            ]);

            DB::beginTransaction();

            $award = Award::create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'type' => $validated['type'] ?? 'achievement',
                'criteria' => $validated['criteria'] ?? null,
                'certificate_template' => $validated['certificate_template'] ?? null,
                'status' => $validated['status'] ?? 1,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Award created successfully.',
                'data' => $award,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Illuminate\Database\QueryException $e) {
            DB::rollBack();
            // Check if it's a table doesn't exist error
            if (str_contains($e->getMessage(), "doesn't exist") || str_contains($e->getMessage(), 'Base table or view not found')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Database table not found. Please run migrations: php artisan migrate',
                    'error' => 'The awards table does not exist. Please run the migration first.',
                ], 500);
            }
            return errorLog("Failed to create award: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        } catch (\Exception $e) {
            DB::rollBack();
            // In development, show the actual error
            if (config('app.debug')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create award',
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ], 500);
            }
            return errorLog("Failed to create award: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    /**
     * Display the specified award.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show($id): JsonResponse
    {
        try {
            $award = Award::withCount('certificates')->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Award fetched successfully.',
                'data' => $award,
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return sendError('error', ['error' => 'Award not found.'], 404);
        } catch (\Exception $e) {
            return errorLog("Failed to fetch award: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    /**
     * Update the specified award.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'type' => 'nullable|string|max:255',
                'criteria' => 'nullable|string',
                'certificate_template' => 'nullable|string|max:255',
                'status' => 'nullable|integer|in:0,1',
            ]);

            DB::beginTransaction();

            $award = Award::findOrFail($id);

            $award->update($validated);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Award updated successfully.',
                'data' => $award,
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return sendError('error', ['error' => 'Award not found.'], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return errorLog("Failed to update award: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    /**
     * Remove the specified award.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $award = Award::findOrFail($id);
            $award->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Award deleted successfully.',
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return sendError('error', ['error' => 'Award not found.'], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            return errorLog("Failed to delete award: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }
}
