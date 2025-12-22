<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Pagination\LengthAwarePaginator;

class AnnouncementController extends Controller
{
    /**
     * Display a listing of announcements.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $search = $request->input('search');
            $status = $request->input('status');
            $perPage = $request->input('per_page', 10);

            $announcements = Announcement::with('roles:id,name')
                ->when($search, function ($query) use ($search) {
                    $query->where(function ($q) use ($search) {
                        $q->where('title', 'LIKE', "%{$search}%")
                          ->orWhere('message', 'LIKE', "%{$search}%");
                    });
                })
                ->when($status, function ($query) use ($status) {
                    $query->where('status', $status);
                })
                ->latest()
                ->paginate($perPage);

            // Transform the data to include target_audience from roles
            $transformedData = $announcements->getCollection()->map(function ($announcement) {
                return [
                    'id' => $announcement->id,
                    'title' => $announcement->title,
                    'message' => $announcement->message,
                    'status' => $announcement->status,
                    'target_audience' => $announcement->roles->map(function ($role) {
                        return [
                            'id' => $role->id,
                            'name' => $role->name,
                        ];
                    }),
                    'roles' => $announcement->roles, // Keep roles for backward compatibility
                    'created_at' => $announcement->created_at,
                    'updated_at' => $announcement->updated_at,
                    'deleted_at' => $announcement->deleted_at,
                ];
            });

            // Create paginated response with transformed data
            $transformedAnnouncements = new LengthAwarePaginator(
                $transformedData,
                $announcements->total(),
                $announcements->perPage(),
                $announcements->currentPage(),
                [
                    'path' => $announcements->path(),
                    'pageName' => $announcements->getPageName(),
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Announcements fetched successfully.',
                'data' => $transformedAnnouncements,
            ], 200);
        } catch (\Illuminate\Database\QueryException $e) {
            // Check if it's a table doesn't exist error
            if (str_contains($e->getMessage(), "doesn't exist") || str_contains($e->getMessage(), 'Base table or view not found')) {
                Log::error("Announcements table doesn't exist. Migration may not have been run. Message => {$e->getMessage()}");
                return response()->json([
                    'success' => false,
                    'message' => 'Database tables not found. Please run the migration: php artisan migrate',
                    'error' => 'Migration required'
                ], 500);
            }
            Log::error("Database error fetching announcements. Message => {$e->getMessage()}");
            return sendError('error', ['error' => 'A database error occurred while fetching announcements.'], 500);
        } catch (\Exception $e) {
            Log::error("Failed to fetch announcements. Message => {$e->getMessage()}, File => {$e->getFile()}, Line => {$e->getLine()}");
            return sendError('error', ['error' => 'An error occurred while fetching announcements.'], 500);
        }
    }

    /**
     * Store a newly created announcement.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'message' => 'required|string',
                'status' => 'required|in:active,inactive',
                'target_audience' => 'required|array|min:1',
                'target_audience.*' => 'exists:roles,id',
            ]);

            DB::beginTransaction();

            $announcement = Announcement::create([
                'title' => $validated['title'],
                'message' => $validated['message'],
                'status' => $validated['status'],
            ]);

            // Attach roles (target audience)
            $announcement->roles()->attach($validated['target_audience']);

            $announcement->load('roles:id,name');

            DB::commit();

            // Transform to include target_audience from roles
            $transformedAnnouncement = [
                'id' => $announcement->id,
                'title' => $announcement->title,
                'message' => $announcement->message,
                'status' => $announcement->status,
                'target_audience' => $announcement->roles->map(function ($role) {
                    return [
                        'id' => $role->id,
                        'name' => $role->name,
                    ];
                }),
                'roles' => $announcement->roles, // Keep roles for backward compatibility
                'created_at' => $announcement->created_at,
                'updated_at' => $announcement->updated_at,
            ];

            return response()->json([
                'success' => true,
                'message' => 'Announcement created successfully.',
                'data' => $transformedAnnouncement,
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
            Log::error("Failed to create announcement. Message => {$e->getMessage()}, File => {$e->getFile()}, Line => {$e->getLine()}");
            return sendError('error', ['error' => 'An error occurred while creating announcement.'], 500);
        }
    }

    /**
     * Display the specified announcement.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show($id): JsonResponse
    {
        try {
            $announcement = Announcement::with('roles:id,name')->findOrFail($id);

            // Transform to include target_audience from roles
            $transformedAnnouncement = [
                'id' => $announcement->id,
                'title' => $announcement->title,
                'message' => $announcement->message,
                'status' => $announcement->status,
                'target_audience' => $announcement->roles->map(function ($role) {
                    return [
                        'id' => $role->id,
                        'name' => $role->name,
                    ];
                }),
                'roles' => $announcement->roles, // Keep roles for backward compatibility
                'created_at' => $announcement->created_at,
                'updated_at' => $announcement->updated_at,
                'deleted_at' => $announcement->deleted_at,
            ];

            return response()->json([
                'success' => true,
                'message' => 'Announcement fetched successfully.',
                'data' => $transformedAnnouncement,
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return sendError('error', ['error' => 'Announcement not found.'], 404);
        } catch (\Exception $e) {
            Log::error("Failed to fetch announcement. Message => {$e->getMessage()}");
            return sendError('error', ['error' => 'An error occurred while fetching announcement.'], 500);
        }
    }

    /**
     * Update the specified announcement.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'title' => 'sometimes|required|string|max:255',
                'message' => 'sometimes|required|string',
                'status' => 'sometimes|required|in:active,inactive',
                'target_audience' => 'sometimes|required|array|min:1',
                'target_audience.*' => 'exists:roles,id',
            ]);

            DB::beginTransaction();

            $announcement = Announcement::findOrFail($id);

            // Update announcement fields
            if (isset($validated['title'])) {
                $announcement->title = $validated['title'];
            }
            if (isset($validated['message'])) {
                $announcement->message = $validated['message'];
            }
            if (isset($validated['status'])) {
                $announcement->status = $validated['status'];
            }

            $announcement->save();

            // Update target audience if provided
            if (isset($validated['target_audience'])) {
                $announcement->roles()->sync($validated['target_audience']);
            }

            $announcement->load('roles:id,name');

            DB::commit();

            // Transform to include target_audience from roles
            $transformedAnnouncement = [
                'id' => $announcement->id,
                'title' => $announcement->title,
                'message' => $announcement->message,
                'status' => $announcement->status,
                'target_audience' => $announcement->roles->map(function ($role) {
                    return [
                        'id' => $role->id,
                        'name' => $role->name,
                    ];
                }),
                'roles' => $announcement->roles, // Keep roles for backward compatibility
                'created_at' => $announcement->created_at,
                'updated_at' => $announcement->updated_at,
                'deleted_at' => $announcement->deleted_at,
            ];

            return response()->json([
                'success' => true,
                'message' => 'Announcement updated successfully.',
                'data' => $transformedAnnouncement,
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return sendError('error', ['error' => 'Announcement not found.'], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to update announcement. Message => {$e->getMessage()}, File => {$e->getFile()}, Line => {$e->getLine()}");
            return sendError('error', ['error' => 'An error occurred while updating announcement.'], 500);
        }
    }

    /**
     * Remove the specified announcement.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $announcement = Announcement::findOrFail($id);
            $announcement->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Announcement deleted successfully.',
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return sendError('error', ['error' => 'Announcement not found.'], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to delete announcement. Message => {$e->getMessage()}, File => {$e->getFile()}, Line => {$e->getLine()}");
            return sendError('error', ['error' => 'An error occurred while deleting announcement.'], 500);
        }
    }

    /**
     * Get all roles for target audience selection.
     * Returns all active roles from the roles table.
     *
     * @return JsonResponse
     */
    public function getTargetAudienceRoles(): JsonResponse
    {
        try {
            // Check if roles table exists
            if (!Schema::hasTable('roles')) {
                Log::error("Roles table doesn't exist. Migration may not have been run.");
                return response()->json([
                    'success' => false,
                    'message' => 'Roles table not found. Please run the migration: php artisan migrate',
                    'data' => [],
                    'error' => 'Migration required'
                ], 500);
            }

            $roles = Role::select('id', 'name')
                ->whereNull('deleted_at') // Only active (non-deleted) roles
                ->orderBy('name', 'asc')
                ->get();

            // Return empty array if no roles found, but still success
            return response()->json([
                'success' => true,
                'message' => $roles->isEmpty() 
                    ? 'No roles found. Please seed the roles table.' 
                    : 'Target audience roles fetched successfully.',
                'data' => $roles,
            ], 200);
        } catch (\Illuminate\Database\QueryException $e) {
            // Check if it's a table doesn't exist error
            if (str_contains($e->getMessage(), "doesn't exist") || str_contains($e->getMessage(), 'Base table or view not found')) {
                Log::error("Roles table doesn't exist. Migration may not have been run. Message => {$e->getMessage()}");
                return response()->json([
                    'success' => false,
                    'message' => 'Roles table not found. Please run the migration: php artisan migrate',
                    'data' => [],
                    'error' => 'Migration required'
                ], 500);
            }
            Log::error("Database error fetching target audience roles. Message => {$e->getMessage()}");
            return sendError('error', ['error' => 'A database error occurred while fetching target audience roles.'], 500);
        } catch (\Exception $e) {
            Log::error("Failed to fetch target audience roles. Message => {$e->getMessage()}, File => {$e->getFile()}, Line => {$e->getLine()}");
            return sendError('error', ['error' => 'An error occurred while fetching target audience roles.'], 500);
        }
    }
}

