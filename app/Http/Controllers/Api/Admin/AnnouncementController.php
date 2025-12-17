<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\StoreAnnouncementRequest;
use App\Http\Requests\Api\Admin\UpdateAnnouncementRequest;
use App\Http\Requests\Api\Admin\ToggleAnnouncementStatusRequest;
use App\Jobs\UploadAnnouncementImageJob;
use App\Models\Announcement;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AnnouncementController extends Controller
{
    /**
     * Display a listing of announcements
     */
    public function index(Request $request)
    {
        try {
            $query = Announcement::with('creator:id,first_name,last_name,email')
                ->orderBy('is_pinned', 'desc')
                ->orderBy('created_at', 'desc');

            // Filter by status
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            // Filter by type
            if ($request->filled('type')) {
                $query->where('type', $request->type);
            }

            // Filter by pinned
            if ($request->filled('is_pinned')) {
                $query->where('is_pinned', filter_var($request->is_pinned, FILTER_VALIDATE_BOOLEAN));
            }

            // Filter by target roles
            if ($request->filled('target_roles')) {
                $targetRoles = is_array($request->target_roles) ? $request->target_roles : [$request->target_roles];
                $query->where(function ($q) use ($targetRoles) {
                    $q->whereNull('target_roles');
                    foreach ($targetRoles as $role) {
                        $q->orWhereJsonContains('target_roles', $role);
                    }
                });
            }

            // Filter by target years
            if ($request->filled('target_years')) {
                $targetYears = is_array($request->target_years) ? $request->target_years : [$request->target_years];
                $query->where(function ($q) use ($targetYears) {
                    $q->whereNull('target_years');
                    foreach ($targetYears as $yearId) {
                        $q->orWhereJsonContains('target_years', (int)$yearId);
                    }
                });
            }

            // Search
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('content', 'like', "%{$search}%");
                });
            }

            // Include trashed
            if ($request->filled('include_trashed') && filter_var($request->include_trashed, FILTER_VALIDATE_BOOLEAN)) {
                $query->withTrashed();
            }

            // Only trashed
            if ($request->filled('only_trashed') && filter_var($request->only_trashed, FILTER_VALIDATE_BOOLEAN)) {
                $query->onlyTrashed();
            }

            // Pagination
            $perPage = $request->integer('per_page', 15);
            $perPage = min($perPage, 100);

            $announcements = $query->paginate($perPage)->through(function ($announcement) {
                // Get all images for this announcement
                $allImages = $announcement->getMedia('announcement_image')->map(function ($media) {
                    return [
                        'id' => $media->id,
                        'url' => $media->getUrl(),
                        'name' => $media->name,
                        'size' => $media->size,
                        'mime_type' => $media->mime_type,
                    ];
                })->toArray();

                // Map target_roles to target_audience (convert to lowercase for frontend)
                $targetAudience = $announcement->target_roles ? array_map(function ($role) {
                    return strtolower($role);
                }, $announcement->target_roles) : null;

                return [
                    'id' => $announcement->id,
                    'title' => $announcement->title,
                    'description' => $announcement->description,
                    'content' => $announcement->content,
                    'message' => $announcement->content, // For frontend compatibility
                    'type' => $announcement->type,
                    'priority' => $announcement->type, // For frontend compatibility
                    'status' => $announcement->status,
                    'published_at' => $announcement->published_at?->format('Y-m-d H:i:s'),
                    'start_date_time' => $announcement->published_at?->format('Y-m-d H:i:s'), // For frontend compatibility
                    'start_datetime' => $announcement->published_at?->format('Y-m-d H:i:s'), // Alternative field name
                    'expires_at' => $announcement->expires_at?->format('Y-m-d H:i:s'),
                    'end_date_time' => $announcement->expires_at?->format('Y-m-d H:i:s'), // For frontend compatibility
                    'end_datetime' => $announcement->expires_at?->format('Y-m-d H:i:s'), // Alternative field name
                    'target_roles' => $announcement->target_roles,
                    'target_audience' => $targetAudience, // For frontend compatibility
                    'target_years' => $announcement->target_years,
                    'class_ids' => $announcement->target_years, // For frontend compatibility
                    'class_id' => $announcement->target_years && count($announcement->target_years) === 1 ? $announcement->target_years[0] : null, // For single class compatibility
                    'is_pinned' => $announcement->is_pinned,
                    'image' => $announcement->getFirstMediaUrl('announcement_image') ?: null, // First image for backward compatibility
                    'images' => $allImages, // All images
                    'images_count' => count($allImages),
                    'created_by' => $announcement->creator ? [
                        'id' => $announcement->creator->id,
                        'name' => $announcement->creator->full_name,
                        'email' => $announcement->creator->email,
                    ] : null,
                    'created_at' => $announcement->created_at->format('Y-m-d H:i:s'),
                    'updated_at' => $announcement->updated_at->format('Y-m-d H:i:s'),
                ];
            });

            return sendResponse($announcements, 'Announcements fetched successfully');
        } catch (Exception $e) {
            return errorLog("Failed to fetch announcements: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    /**
     * Store a newly created announcement
     */
    public function store(StoreAnnouncementRequest $request)
    {
        DB::beginTransaction();
        try {
            $data = $request->validated();
            // Remove image fields from data array as they're handled separately
            unset($data['announcement_image'], $data['announcement_images']);
            $data['created_by'] = Auth::id();

            // Map frontend fields to backend fields
            // Map start_date_time to published_at
            if (isset($data['start_date_time'])) {
                $data['published_at'] = $data['start_date_time'];
                unset($data['start_date_time']);
            }

            // Map end_date_time to expires_at
            if (isset($data['end_date_time'])) {
                $data['expires_at'] = $data['end_date_time'];
                unset($data['end_date_time']);
            }

            // Map target_audience to target_roles (convert to proper case)
            if (isset($data['target_audience'])) {
                $targetAudience = $data['target_audience'];
                if (is_array($targetAudience)) {
                    // Convert lowercase to proper case: admin -> Admin, student -> Student, etc.
                    $data['target_roles'] = array_map(function ($role) {
                        return ucfirst(strtolower($role));
                    }, $targetAudience);
                }
                unset($data['target_audience']);
            }

            // Map class_ids to target_years
            if (isset($data['class_ids'])) {
                $data['target_years'] = $data['class_ids'];
                unset($data['class_ids']);
            }

            // Map priority to type
            if (isset($data['priority'])) {
                $data['type'] = $data['priority'];
                unset($data['priority']);
            }

            // Map message to content if content is not set
            if (isset($data['message']) && !isset($data['content'])) {
                $data['content'] = $data['message'];
                unset($data['message']);
            }

            // Set default published_at if not provided
            if (!isset($data['published_at'])) {
                $data['published_at'] = now();
            }

            $announcement = Announcement::create($data);

            // Handle single image upload (for backward compatibility)
            if ($request->hasFile('announcement_image')) {
                try {
                    Log::info("Single image file detected in create request, processing upload");
                    $file = $request->file('announcement_image');
                    $tempPath = $file->store('temp/announcement_images', 'local');

                    if ($tempPath) {
                        Log::info("Temporary file stored", ['temp_path' => $tempPath]);
                        UploadAnnouncementImageJob::dispatch(
                            $announcement->id,
                            $tempPath,
                            $file->getClientOriginalName(),
                            $file->getMimeType()
                        );
                        Log::info("UploadAnnouncementImageJob dispatched for announcement ID: {$announcement->id}");
                    }
                } catch (Exception $e) {
                    Log::error("Failed to process single image upload", ['error' => $e->getMessage()]);
                }
            }

            // Handle multiple images upload
            if ($request->hasFile('announcement_images')) {
                try {
                    Log::info("Multiple image files detected in create request, processing uploads");
                    $files = $request->file('announcement_images');

                    foreach ($files as $file) {
                        $tempPath = $file->store('temp/announcement_images', 'local');

                        if ($tempPath) {
                            Log::info("Temporary file stored", ['temp_path' => $tempPath]);
                            UploadAnnouncementImageJob::dispatch(
                                $announcement->id,
                                $tempPath,
                                $file->getClientOriginalName(),
                                $file->getMimeType()
                            );
                        }
                    }
                    Log::info("UploadAnnouncementImageJob dispatched for " . count($files) . " images, announcement ID: {$announcement->id}");
                } catch (Exception $e) {
                    Log::error("Failed to process multiple images upload", ['error' => $e->getMessage()]);
                }
            }

            DB::commit();

            $announcement->load('creator:id,first_name,last_name,email');

            $responseData = [
                'id' => $announcement->id,
                'title' => $announcement->title,
                'description' => $announcement->description,
                'content' => $announcement->content,
                'type' => $announcement->type,
                'status' => $announcement->status,
                'published_at' => $announcement->published_at?->format('Y-m-d H:i:s'),
                'expires_at' => $announcement->expires_at?->format('Y-m-d H:i:s'),
                'target_roles' => $announcement->target_roles,
                'target_years' => $announcement->target_years,
                'is_pinned' => $announcement->is_pinned,
                'image' => $announcement->getFirstMediaUrl('announcement_image') ?: null,
                'created_by' => $announcement->creator ? [
                    'id' => $announcement->creator->id,
                    'name' => $announcement->creator->full_name,
                    'email' => $announcement->creator->email,
                ] : null,
                'created_at' => $announcement->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $announcement->updated_at->format('Y-m-d H:i:s'),
            ];

            return sendResponse($responseData, 'Announcement created successfully', 201);
        } catch (Exception $e) {
            DB::rollBack();
            return errorLog("Failed to create announcement: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    /**
     * Display the specified announcement
     */
    public function show($id)
    {
        try {
            $announcement = Announcement::with('creator:id,first_name,last_name,email')
                ->findOrFail($id);

            $responseData = [
                'id' => $announcement->id,
                'title' => $announcement->title,
                'description' => $announcement->description,
                'content' => $announcement->content,
                'type' => $announcement->type,
                'status' => $announcement->status,
                'published_at' => $announcement->published_at?->format('Y-m-d H:i:s'),
                'expires_at' => $announcement->expires_at?->format('Y-m-d H:i:s'),
                'target_roles' => $announcement->target_roles,
                'target_years' => $announcement->target_years,
                'is_pinned' => $announcement->is_pinned,
                'image' => $announcement->getFirstMediaUrl('announcement_image') ?: null,
                'created_by' => $announcement->creator ? [
                    'id' => $announcement->creator->id,
                    'name' => $announcement->creator->full_name,
                    'email' => $announcement->creator->email,
                ] : null,
                'created_at' => $announcement->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $announcement->updated_at->format('Y-m-d H:i:s'),
            ];

            return sendResponse($responseData, 'Announcement fetched successfully');
        } catch (Exception $e) {
            return errorLog("Failed to fetch announcement: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    /**
     * Update the specified announcement
     */
    public function update(UpdateAnnouncementRequest $request, $id)
    {
        DB::beginTransaction();
        try {
            $announcement = Announcement::findOrFail($id);
            $data = $request->validated();

            // Remove image fields from data array as they're handled separately
            unset($data['announcement_image'], $data['announcement_images']);

            $announcement->update($data);

            // Handle single image upload (replaces all existing images if provided)
            if ($request->hasFile('announcement_image')) {
                try {
                    Log::info("Single image file detected in update request, processing upload");

                    // Delete existing images if any
                    $announcement->clearMediaCollection('announcement_image');

                    $file = $request->file('announcement_image');
                    $tempPath = $file->store('temp/announcement_images', 'local');

                    if ($tempPath) {
                        Log::info("Temporary file stored", ['temp_path' => $tempPath]);
                        UploadAnnouncementImageJob::dispatch(
                            $announcement->id,
                            $tempPath,
                            $file->getClientOriginalName(),
                            $file->getMimeType()
                        );
                        Log::info("UploadAnnouncementImageJob dispatched for announcement ID: {$announcement->id}");
                    }
                } catch (Exception $e) {
                    Log::error("Failed to process single image upload", ['error' => $e->getMessage()]);
                }
            }

            // Handle multiple images upload (adds to existing images)
            if ($request->hasFile('announcement_images')) {
                try {
                    Log::info("Multiple image files detected in update request, processing uploads");
                    $files = $request->file('announcement_images');

                    foreach ($files as $file) {
                        $tempPath = $file->store('temp/announcement_images', 'local');

                        if ($tempPath) {
                            Log::info("Temporary file stored", ['temp_path' => $tempPath]);
                            UploadAnnouncementImageJob::dispatch(
                                $announcement->id,
                                $tempPath,
                                $file->getClientOriginalName(),
                                $file->getMimeType()
                            );
                        }
                    }
                    Log::info("UploadAnnouncementImageJob dispatched for " . count($files) . " images, announcement ID: {$announcement->id}");
                } catch (Exception $e) {
                    Log::error("Failed to process multiple images upload", ['error' => $e->getMessage()]);
                }
            }

            DB::commit();

            $announcement->load('creator:id,first_name,last_name,email');

            // Get all images for this announcement
            $allImages = $announcement->getMedia('announcement_image')->map(function ($media) {
                return [
                    'id' => $media->id,
                    'url' => $media->getUrl(),
                    'name' => $media->name,
                    'size' => $media->size,
                    'mime_type' => $media->mime_type,
                ];
            })->toArray();

            $responseData = [
                'id' => $announcement->id,
                'title' => $announcement->title,
                'description' => $announcement->description,
                'content' => $announcement->content,
                'type' => $announcement->type,
                'status' => $announcement->status,
                'published_at' => $announcement->published_at?->format('Y-m-d H:i:s'),
                'expires_at' => $announcement->expires_at?->format('Y-m-d H:i:s'),
                'target_roles' => $announcement->target_roles,
                'target_years' => $announcement->target_years,
                'is_pinned' => $announcement->is_pinned,
                'image' => $announcement->getFirstMediaUrl('announcement_image') ?: null, // First image for backward compatibility
                'images' => $allImages, // All images
                'images_count' => count($allImages),
                'created_by' => $announcement->creator ? [
                    'id' => $announcement->creator->id,
                    'name' => $announcement->creator->full_name,
                    'email' => $announcement->creator->email,
                ] : null,
                'created_at' => $announcement->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $announcement->updated_at->format('Y-m-d H:i:s'),
            ];

            return sendResponse($responseData, 'Announcement updated successfully');
        } catch (Exception $e) {
            DB::rollBack();
            return errorLog("Failed to update announcement: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    /**
     * Remove the specified announcement
     */
    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $announcement = Announcement::findOrFail($id);
            $announcement->delete();

            DB::commit();

            return sendResponse('delete', 'Announcement deleted successfully');
        } catch (Exception $e) {
            DB::rollBack();
            return errorLog("Failed to delete announcement: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    /**
     * Toggle announcement status
     */
    public function toggleStatus(ToggleAnnouncementStatusRequest $request, $id)
    {
        DB::beginTransaction();
        try {
            $announcement = Announcement::findOrFail($id);
            $announcement->status = $request->status;
            $announcement->save();

            DB::commit();

            $announcement->load('creator:id,first_name,last_name,email');

            $responseData = [
                'id' => $announcement->id,
                'title' => $announcement->title,
                'status' => $announcement->status,
                'image' => $announcement->getFirstMediaUrl('announcement_image') ?: null,
            ];

            return sendResponse($responseData, 'Announcement status updated successfully');
        } catch (Exception $e) {
            DB::rollBack();
            return errorLog("Failed to toggle announcement status: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    /**
     * Toggle pinned status
     */
    public function togglePinned($id)
    {
        DB::beginTransaction();
        try {
            $announcement = Announcement::findOrFail($id);
            $announcement->is_pinned = !$announcement->is_pinned;
            $announcement->save();

            DB::commit();

            $announcement->load('creator:id,first_name,last_name,email');

            $responseData = [
                'id' => $announcement->id,
                'title' => $announcement->title,
                'is_pinned' => $announcement->is_pinned,
                'image' => $announcement->getFirstMediaUrl('announcement_image') ?: null,
            ];

            return sendResponse($responseData, 'Announcement pinned status updated successfully');
        } catch (Exception $e) {
            DB::rollBack();
            return errorLog("Failed to toggle pinned status: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    /**
     * Restore a soft-deleted announcement
     */
    public function restore($id)
    {
        DB::beginTransaction();
        try {
            $announcement = Announcement::withTrashed()->findOrFail($id);

            if (!$announcement->trashed()) {
                return sendError('Announcement is not deleted', [], 400);
            }

            $announcement->restore();

            DB::commit();

            $announcement->load('creator:id,first_name,last_name,email');

            $responseData = [
                'id' => $announcement->id,
                'title' => $announcement->title,
                'image' => $announcement->getFirstMediaUrl('announcement_image') ?: null,
            ];

            return sendResponse($responseData, 'Announcement restored successfully');
        } catch (Exception $e) {
            DB::rollBack();
            return errorLog("Failed to restore announcement: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }
}
