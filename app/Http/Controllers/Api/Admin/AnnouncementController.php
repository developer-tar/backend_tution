<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\StoreAnnouncementRequest;
use App\Http\Requests\Api\Admin\UpdateAnnouncementRequest;
use App\Http\Requests\Api\Admin\ToggleAnnouncementStatusRequest;
use App\Jobs\UploadAnnouncementImageJob;
use App\Models\Announcement;
use App\Models\CourseTimeSlot;
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

            // Filter by target roles or target_audience (frontend sends target_audience)
            $targetRolesToFilter = null;
            if ($request->filled('target_roles')) {
                $targetRolesToFilter = is_array($request->target_roles) ? $request->target_roles : [$request->target_roles];
            } elseif ($request->filled('target_audience')) {
                // Convert target_audience (lowercase) to target_roles (proper case)
                $targetAudience = is_array($request->target_audience) ? $request->target_audience : [$request->target_audience];
                $targetRolesToFilter = array_map(function ($audience) {
                    // Convert lowercase to proper case: admin -> Admin, parent -> Parent, student -> Student
                    return ucfirst(strtolower($audience));
                }, $targetAudience);
            }

            if ($targetRolesToFilter) {
                $query->where(function ($q) use ($targetRolesToFilter) {
                    $q->whereNull('target_roles');
                    foreach ($targetRolesToFilter as $role) {
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

                // Get all PDFs for this announcement
                $allPdfs = $announcement->getMedia('announcement_pdf')->map(function ($media) {
                    return [
                        'id' => $media->id,
                        'url' => $media->getUrl(),
                        'name' => $media->name,
                        'size' => $media->size,
                        'mime_type' => $media->mime_type,
                    ];
                })->toArray();

                // Get all timetables for this announcement
                $allTimetables = $announcement->getMedia('announcement_timetable')->map(function ($media) {
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
                    'course_time_slot_ids' => $announcement->course_time_slot_ids ?? [],
                    'is_pinned' => $announcement->is_pinned,
                    'image' => $announcement->getFirstMediaUrl('announcement_image') ?: null, // First image for backward compatibility
                    'images' => $allImages, // All images
                    'images_count' => count($allImages),
                    'pdfs' => $allPdfs, // All PDFs
                    'pdfs_count' => count($allPdfs),
                    'timetables' => $allTimetables, // All timetables
                    'timetables_count' => count($allTimetables),
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
            // Remove file fields from data array as they're handled separately
            unset($data['announcement_image'], $data['announcement_images'], $data['announcement_pdfs'], $data['announcement_timetables']);
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

            // class_ids removed - using course_time_slots instead
            // Set target_years to empty array if not provided
            if (!isset($data['target_years'])) {
                $data['target_years'] = [];
            }

            // Ensure course_time_slot_ids is properly set as array
            if (isset($data['course_time_slot_ids'])) {
                // Ensure it's an array and convert string IDs to integers
                if (is_array($data['course_time_slot_ids'])) {
                    $data['course_time_slot_ids'] = array_map('intval', array_filter($data['course_time_slot_ids']));
                } else {
                    $data['course_time_slot_ids'] = [];
                }
            } else {
                $data['course_time_slot_ids'] = [];
            }

            // Map priority to type
            if (isset($data['priority'])) {
                $data['type'] = $data['priority'];
                unset($data['priority']);
            }

            // Map message to content (frontend sends 'message', backend stores as 'content')
            if (isset($data['message'])) {
                $data['content'] = $data['message'];
                unset($data['message']);
            }

            // Handle is_pinned - convert string to boolean
            if (isset($data['is_pinned'])) {
                $data['is_pinned'] = filter_var($data['is_pinned'], FILTER_VALIDATE_BOOLEAN);
            } else {
                $data['is_pinned'] = false;
            }

            // Set default published_at if not provided
            if (!isset($data['published_at'])) {
                $data['published_at'] = now();
            }

            // Set default status if not provided
            if (!isset($data['status'])) {
                $data['status'] = 2; // Approved
            }

            Log::info("Creating announcement with data:", $data);

            try {
                $announcement = Announcement::create($data);
            } catch (\Exception $e) {
                Log::error("Failed to create announcement in database", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'data' => $data
                ]);
                throw $e;
            }

            Log::info("Announcement created successfully", ['id' => $announcement->id]);

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

            // Handle multiple PDF uploads
            if ($request->hasFile('announcement_pdfs')) {
                try {
                    Log::info("Multiple PDF files detected in create request, processing uploads");
                    $files = $request->file('announcement_pdfs');
                    foreach ($files as $file) {
                        $tempPath = $file->store('temp/announcement_pdfs', 'local');
                        if ($tempPath) {
                            UploadAnnouncementImageJob::dispatch(
                                $announcement->id,
                                $tempPath,
                                $file->getClientOriginalName(),
                                $file->getMimeType()
                            );
                        }
                    }
                } catch (Exception $e) {
                    Log::error("Failed to process multiple PDF uploads", ['error' => $e->getMessage()]);
                }
            }

            // Handle multiple timetable uploads
            if ($request->hasFile('announcement_timetables')) {
                try {
                    Log::info("Multiple timetable files detected in create request, processing uploads");
                    $files = $request->file('announcement_timetables');
                    foreach ($files as $file) {
                        $tempPath = $file->store('temp/announcement_timetables', 'local');
                        if ($tempPath) {
                            UploadAnnouncementImageJob::dispatch(
                                $announcement->id,
                                $tempPath,
                                $file->getClientOriginalName(),
                                $file->getMimeType()
                            );
                        }
                    }
                } catch (Exception $e) {
                    Log::error("Failed to process multiple timetable uploads", ['error' => $e->getMessage()]);
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
            Log::error("Failed to create announcement", [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
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

            // Remove file fields from data array as they're handled separately
            unset($data['announcement_image'], $data['announcement_images'], $data['announcement_pdfs'], $data['announcement_timetables']);

            // Map frontend fields to backend fields (same as store method)
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

            // class_ids removed - using course_time_slots instead
            // Set target_years to empty array if not provided
            if (!isset($data['target_years'])) {
                $data['target_years'] = [];
            }

            // Ensure course_time_slot_ids is properly set as array
            if (isset($data['course_time_slot_ids'])) {
                // Ensure it's an array and convert string IDs to integers
                if (is_array($data['course_time_slot_ids'])) {
                    $data['course_time_slot_ids'] = array_map('intval', array_filter($data['course_time_slot_ids']));
                } else {
                    $data['course_time_slot_ids'] = [];
                }
            } else {
                // If not provided in update, keep existing value (don't set to empty)
                // Only set to empty if explicitly provided as empty array
                if (!array_key_exists('course_time_slot_ids', $data)) {
                    // Don't update course_time_slot_ids if not provided
                    unset($data['course_time_slot_ids']);
                } else {
                    $data['course_time_slot_ids'] = [];
                }
            }

            // Map priority to type
            if (isset($data['priority'])) {
                $data['type'] = $data['priority'];
                unset($data['priority']);
            }

            // Map message to content (frontend sends 'message', backend stores as 'content')
            if (isset($data['message'])) {
                Log::info("Message field received in update request", [
                    'announcement_id' => $announcement->id,
                    'message_length' => strlen($data['message'] ?? ''),
                    'message_preview' => substr($data['message'] ?? '', 0, 100)
                ]);
                $data['content'] = $data['message'];
                unset($data['message']);
            } else {
                Log::warning("Message field NOT found in update request data", [
                    'announcement_id' => $announcement->id,
                    'available_keys' => array_keys($data),
                    'has_content' => isset($data['content'])
                ]);
            }

            Log::info("Updating announcement with data:", [
                'announcement_id' => $announcement->id,
                'title' => $data['title'] ?? 'N/A',
                'content' => isset($data['content']) ? (strlen($data['content']) . ' characters') : 'NOT SET',
                'content_preview' => isset($data['content']) ? substr($data['content'], 0, 100) : 'N/A',
                'type' => $data['type'] ?? 'N/A',
                'course_time_slot_ids' => isset($data['course_time_slot_ids']) ? $data['course_time_slot_ids'] : 'NOT UPDATED',
                'course_time_slot_ids_count' => isset($data['course_time_slot_ids']) ? count($data['course_time_slot_ids']) : 'N/A'
            ]);

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

            // Handle multiple PDF uploads
            if ($request->hasFile('announcement_pdfs')) {
                try {
                    Log::info("Multiple PDF files detected in update request, processing uploads");
                    // Clear existing PDFs if new ones are provided
                    $announcement->clearMediaCollection('announcement_pdf');

                    $files = $request->file('announcement_pdfs');
                    foreach ($files as $file) {
                        $tempPath = $file->store('temp/announcement_pdfs', 'local');
                        if ($tempPath) {
                            UploadAnnouncementImageJob::dispatch(
                                $announcement->id,
                                $tempPath,
                                $file->getClientOriginalName(),
                                $file->getMimeType()
                            );
                        }
                    }
                } catch (Exception $e) {
                    Log::error("Failed to process multiple PDF uploads", ['error' => $e->getMessage()]);
                }
            }

            // Handle multiple timetable uploads
            if ($request->hasFile('announcement_timetables')) {
                try {
                    Log::info("Multiple timetable files detected in update request, processing uploads");
                    // Clear existing timetables if new ones are provided
                    $announcement->clearMediaCollection('announcement_timetable');

                    $files = $request->file('announcement_timetables');
                    foreach ($files as $file) {
                        $tempPath = $file->store('temp/announcement_timetables', 'local');
                        if ($tempPath) {
                            UploadAnnouncementImageJob::dispatch(
                                $announcement->id,
                                $tempPath,
                                $file->getClientOriginalName(),
                                $file->getMimeType()
                            );
                        }
                    }
                } catch (Exception $e) {
                    Log::error("Failed to process multiple timetable uploads", ['error' => $e->getMessage()]);
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

    /**
     * Get all course time slots for announcement form
     * Fetches from the course_time_slots table via CourseTimeSlot model
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCourseTimeSlots()
    {
        try {
            Log::info("Fetching course time slots for announcements from course_time_slots table");

            // First, check if there's any data in the table at all
            $totalCount = CourseTimeSlot::withTrashed()->count();
            $activeCount = CourseTimeSlot::whereNull('deleted_at')->count();

            Log::info("Course time slots table statistics", [
                'total_records' => $totalCount,
                'active_records' => $activeCount,
                'deleted_records' => $totalCount - $activeCount
            ]);

            // This method queries the course_time_slots table via CourseTimeSlot model
            $timeSlots = CourseTimeSlot::getAllForAnnouncements();

            // Convert collection to array if needed
            $timeSlotsArray = [];
            if ($timeSlots instanceof \Illuminate\Support\Collection) {
                // Convert collection to array - values() ensures numeric keys
                $timeSlotsArray = $timeSlots->values()->toArray();
                Log::info("Converted Collection to array", [
                    'collection_count' => $timeSlots->count(),
                    'array_count' => count($timeSlotsArray)
                ]);
            } elseif (is_array($timeSlots)) {
                // Ensure it's a numeric array (not associative)
                $timeSlotsArray = array_values($timeSlots);
                Log::info("Converted array to numeric array", [
                    'original_count' => count($timeSlots),
                    'converted_count' => count($timeSlotsArray)
                ]);
            } else {
                Log::warning("Course time slots returned unexpected type: " . gettype($timeSlots));
                $timeSlotsArray = [];
            }

            // If no slots found, try a direct query as fallback for debugging
            if (empty($timeSlotsArray)) {
                Log::warning("No time slots found via getAllForAnnouncements(), trying direct query");
                $directQuery = \DB::table('course_time_slots')
                    ->whereNull('deleted_at')
                    ->count();
                Log::info("Direct query result from course_time_slots table", [
                    'count' => $directQuery
                ]);
            }

            Log::info("Course time slots fetched successfully", [
                'count' => count($timeSlotsArray),
                'is_array' => is_array($timeSlotsArray),
                'is_numeric_array' => count($timeSlotsArray) > 0 ? array_keys($timeSlotsArray) === range(0, count($timeSlotsArray) - 1) : true,
                'sample' => count($timeSlotsArray) > 0 ? $timeSlotsArray[0] : null,
                'first_item_keys' => count($timeSlotsArray) > 0 ? array_keys($timeSlotsArray[0]) : []
            ]);

            // Always return success, even if array is empty (no slots is valid)
            return sendResponse($timeSlotsArray, count($timeSlotsArray) > 0
                ? 'Course time slots fetched successfully'
                : 'No course time slots found');
        } catch (\Exception $e) {
            Log::error("Failed to fetch course time slots: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
            Log::error("Stack trace: " . $e->getTraceAsString());

            // Return empty array instead of error to prevent frontend issues
            // The frontend will show "No course time slots available" message
            return sendResponse([], 'No course time slots found');
        }
    }

    /**
     * Get distinct class names from course_time_slots for class selection
     */
    public function getClassNames()
    {
        try {
            $classNames = CourseTimeSlot::select('class_name')
                ->whereNotNull('class_name')
                ->where('class_name', '!=', '')
                ->distinct()
                ->orderBy('class_name')
                ->get()
                ->values()
                ->map(function ($slot, $index) {
                    return [
                        'id' => $index + 1,
                        'name' => $slot->class_name,
                        'class_name' => $slot->class_name,
                    ];
                });

            return sendResponse($classNames, 'Class names fetched successfully');
        } catch (Exception $e) {
            Log::error("Failed to fetch class names: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
            return errorLog("Failed to fetch class names: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }
}
