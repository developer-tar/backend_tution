<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\IndexAwardRequest;
use App\Http\Requests\Api\Admin\StoreAwardRequest;
use App\Http\Requests\Api\Admin\ShowAwardRequest;
use App\Http\Requests\Api\Admin\UpdateAwardRequest;
use App\Http\Requests\Api\Admin\DestroyAwardRequest;
use App\Models\Award;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Pagination\LengthAwarePaginator;

class AwardController extends Controller
{
    /**
     * Display a listing of awards.
     *
     * @param IndexAwardRequest $request
     * @return JsonResponse
     */
    public function index(IndexAwardRequest $request): JsonResponse
    {
        // Check if awards table exists
        if (!Schema::hasTable('awards')) {
            return sendError('Database table not found', ['error' => 'The awards table does not exist. Please run the migration: php artisan migrate'], 500);
        }

        try {
            $validated = $request->validated();
            $search = $validated['search'] ?? null;
            $status = $validated['status'] ?? null;
            $type = $validated['type'] ?? null;
            $perPage = $validated['per_page'] ?? 10;

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

            return sendResponse($awards, 'Awards fetched successfully.', 200);
        } catch (\Exception $e) {
            return errorLog("Failed to fetch awards: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    /**
     * Store a newly created award.
     *
     * @param StoreAwardRequest $request
     * @return JsonResponse
     */
    public function store(StoreAwardRequest $request): JsonResponse
    {
        // Check if awards table exists
        if (!Schema::hasTable('awards')) {
            return sendError('Database table not found', ['error' => 'The awards table does not exist. Please run the migration: php artisan migrate'], 500);
        }

        try {
            $validated = $request->validated();

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

            return sendResponse($award, 'Award created successfully.', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return sendError('Validation failed', $e->errors(), 422);
        } catch (\Illuminate\Database\QueryException $e) {
            DB::rollBack();
            // Check if it's a table doesn't exist error
            if (str_contains($e->getMessage(), "doesn't exist") || str_contains($e->getMessage(), 'Base table or view not found')) {
                return sendError('Database table not found. Please run migrations: php artisan migrate', ['error' => 'The awards table does not exist. Please run the migration first.'], 500);
            }
            return errorLog("Failed to create award: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        } catch (\Exception $e) {
            DB::rollBack();
            // In development, show the actual error
            if (config('app.debug')) {
                return sendError('Failed to create award', [
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
     * @param ShowAwardRequest $request
     * @return JsonResponse
     */
    public function show(ShowAwardRequest $request): JsonResponse
    {
        try {
            $id = $request->route('award') ?? $request->route('id');
            $award = Award::withCount('certificates')->findOrFail($id);

            return sendResponse($award, 'Award fetched successfully.', 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return sendError('error', ['error' => 'Award not found.'], 404);
        } catch (\Exception $e) {
            return errorLog("Failed to fetch award: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    /**
     * Update the specified award.
     *
     * @param UpdateAwardRequest $request
     * @return JsonResponse
     */
    public function update(UpdateAwardRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            // Remove award ID from validated data as it's only for validation
            unset($validated['award']);

            DB::beginTransaction();

            $id = $request->route('award') ?? $request->route('id');
            $award = Award::findOrFail($id);

            $award->update($validated);

            DB::commit();

            return sendResponse($award, 'Award updated successfully.', 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return sendError('error', ['error' => 'Award not found.'], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return sendError('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return errorLog("Failed to update award: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    /**
     * Remove the specified award.
     *
     * @param DestroyAwardRequest $request
     * @return JsonResponse
     */
    public function destroy(DestroyAwardRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $id = $request->route('award') ?? $request->route('id');
            $award = Award::findOrFail($id);
            $award->delete();

            DB::commit();

            return sendResponse('delete', 'Award deleted successfully.', 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return sendError('error', ['error' => 'Award not found.'], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            return errorLog("Failed to delete award: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }
}
