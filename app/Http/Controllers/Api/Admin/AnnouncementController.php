<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\IndexAnnouncementRequest;
use App\Http\Requests\Api\Admin\StoreAnnouncementRequest;
use App\Http\Requests\Api\Admin\ShowAnnouncementRequest;
use App\Http\Requests\Api\Admin\UpdateAnnouncementRequest;
use App\Http\Requests\Api\Admin\DestroyAnnouncementRequest;
use App\Http\Requests\Api\Admin\GetFilteredItemsRequest;
use App\Http\Requests\Api\Admin\GetClassesRequest;
use App\Models\Announcement;
use App\Models\Role;
use App\Models\User;
use App\Models\AcdemicYear;
use App\Models\Course;
use App\Models\Paper;
use App\Models\MockExam;
use App\Models\CourseTimeSlot;
use App\Models\AcdemicCourse;
use App\Models\Module;
use App\Notifications\AnnouncementNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Pagination\LengthAwarePaginator;

class AnnouncementController extends Controller
{
    /**
     * Display a listing of announcements.
     *
     * @param IndexAnnouncementRequest $request
     * @return JsonResponse
     */
    public function index(IndexAnnouncementRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $search = $validated['search'] ?? null;
            $status = $validated['status'] ?? null;
            $perPage = $validated['per_page'] ?? 10;

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

            return sendResponse($transformedAnnouncements, 'Announcements fetched successfully.', 200);
        } catch (\Illuminate\Database\QueryException $e) {
            // Check if it's a table doesn't exist error
            if (str_contains($e->getMessage(), "doesn't exist") || str_contains($e->getMessage(), 'Base table or view not found')) {
                errorLog("Announcements table doesn't exist. Migration may not have been run. Message => {$e->getMessage()}");
                return sendError('Database tables not found. Please run the migration: php artisan migrate', ['error' => 'Migration required'], 500);
            }
            return errorLog("Database error fetching announcements: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        } catch (\Exception $e) {
            return errorLog("Failed to fetch announcements: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    /**
     * Store a newly created announcement.
     *
     * @param StoreAnnouncementRequest $request
     * @return JsonResponse
     */
    public function store(StoreAnnouncementRequest $request): JsonResponse
    {
        try {
            // Get admin role ID to exclude it
            $adminRole = Role::where('name', config('constants.roles.ADMIN'))->first();
            $adminRoleId = $adminRole ? $adminRole->id : null;

            $validated = $request->validated();

            // Remove admin role from target audience if present (double check)
            if ($adminRoleId) {
                $validated['target_audience'] = array_values(array_filter($validated['target_audience'], function ($roleId) use ($adminRoleId) {
                    return $roleId != $adminRoleId;
                }));
            }

            // Validate that at least one role remains after filtering
            if (empty($validated['target_audience'])) {
                return sendError('Validation failed', ['target_audience' => ['At least one target audience role (excluding Admin) is required.']], 422);
            }

            DB::beginTransaction();

            $announcement = Announcement::create([
                'title' => $validated['title'],
                'message' => $validated['message'],
                'status' => $validated['status'],
                'module_id' => $validated['module_id'] ?? null,
                'academic_year_id' => $validated['academic_year_id'] ?? null,
                'course_id' => $validated['course_id'] ?? null,
                'class_id' => $validated['class_id'] ?? null,
            ]);

            // Attach roles (target audience)
            $announcement->roles()->attach($validated['target_audience']);

            $announcement->load('roles:id,name');

            DB::commit();

            // Send notifications if announcement is published
            if ($announcement->status == config('constants.announcement_status.publish')) {
                $this->sendAnnouncementNotifications($announcement);
            }

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

            return sendResponse($transformedAnnouncement, 'Announcement created successfully.', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return sendError('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return errorLog("Failed to create announcement: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    /**
     * Display the specified announcement.
     *
     * @param ShowAnnouncementRequest $request
     * @return JsonResponse
     */
    public function show(ShowAnnouncementRequest $request): JsonResponse
    {
        try {
            $id = $request->route('announcement') ?? $request->route('id');
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

            return sendResponse($transformedAnnouncement, 'Announcement fetched successfully.', 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return sendError('error', ['error' => 'Announcement not found.'], 404);
        } catch (\Exception $e) {
            return errorLog("Failed to fetch announcement: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    /**
     * Update the specified announcement.
     *
     * @param UpdateAnnouncementRequest $request
     * @return JsonResponse
     */
    public function update(UpdateAnnouncementRequest $request): JsonResponse
    {
        try {
            // Get admin role ID to exclude it
            $adminRole = Role::where('name', config('constants.roles.ADMIN'))->first();
            $adminRoleId = $adminRole ? $adminRole->id : null;

            $validated = $request->validated();

            // Remove announcement ID from validated data as it's only for validation
            unset($validated['announcement']);

            // Remove admin role from target audience if present (double check)
            if (isset($validated['target_audience']) && $adminRoleId) {
                $validated['target_audience'] = array_values(array_filter($validated['target_audience'], function ($roleId) use ($adminRoleId) {
                    return $roleId != $adminRoleId;
                }));

                // Validate that at least one role remains after filtering
                if (empty($validated['target_audience'])) {
                    return sendError('Validation failed', ['target_audience' => ['At least one target audience role (excluding Admin) is required.']], 422);
                }
            }

            DB::beginTransaction();

            $id = $request->route('announcement') ?? $request->route('id');
            $announcement = Announcement::findOrFail($id);

            // Store original status before updating
            $originalStatus = $announcement->status;

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
            if (isset($validated['module_id'])) {
                $announcement->module_id = $validated['module_id'];
            }
            if (isset($validated['academic_year_id'])) {
                $announcement->academic_year_id = $validated['academic_year_id'];
            }
            if (isset($validated['course_id'])) {
                $announcement->course_id = $validated['course_id'];
            }
            if (isset($validated['class_id'])) {
                $announcement->class_id = $validated['class_id'];
            }

            $announcement->save();

            // Update target audience if provided
            if (isset($validated['target_audience'])) {
                $announcement->roles()->sync($validated['target_audience']);
            }

            $announcement->load('roles:id,name');

            // Check if status changed to published
            $wasPublished = $originalStatus == config('constants.announcement_status.publish');
            $isNowPublished = $announcement->status == config('constants.announcement_status.publish');
            $statusChangedToPublished = !$wasPublished && $isNowPublished;

            DB::commit();

            // Send notifications if announcement is newly published
            if ($statusChangedToPublished || ($isNowPublished && isset($validated['target_audience']))) {
                $this->sendAnnouncementNotifications($announcement);
            }

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

            return sendResponse($transformedAnnouncement, 'Announcement updated successfully.', 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return sendError('error', ['error' => 'Announcement not found.'], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return sendError('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return errorLog("Failed to update announcement: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    /**
     * Remove the specified announcement.
     *
     * @param DestroyAnnouncementRequest $request
     * @return JsonResponse
     */
    public function destroy(DestroyAnnouncementRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $id = $request->route('announcement') ?? $request->route('id');
            $announcement = Announcement::findOrFail($id);
            $announcement->delete();

            DB::commit();

            return sendResponse('delete', 'Announcement deleted successfully.', 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return sendError('error', ['error' => 'Announcement not found.'], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            return errorLog("Failed to delete announcement: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    /**
     * Get all academic years for course year selection.
     *
     * @return JsonResponse
     */
    public function getAcademicYears(): JsonResponse
    {
        try {
            $academicYears = AcdemicYear::select('id', 'start_year', 'end_year')
                ->whereNull('deleted_at')
                ->orderBy('start_year', 'desc')
                ->get()
                ->map(function ($year) {
                    return [
                        'id' => $year->id,
                        'name' => $year->start_end_year, // Using the accessor
                        'start_year' => $year->start_year,
                        'end_year' => $year->end_year,
                    ];
                });

            return sendResponse($academicYears, 'Academic years fetched successfully.', 200);
        } catch (\Exception $e) {
            return errorLog("Failed to fetch academic years: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    /**
     * Get all module modes for selection.
     *
     * @return JsonResponse
     */
    public function getModuleModes(): JsonResponse
    {
        try {
            $modes = Module::active()
                ->select('id', 'name', 'description')
                ->orderBy('name', 'asc')
                ->get();

            return sendResponse($modes, 'Module modes fetched successfully.', 200);
        } catch (\Exception $e) {
            return errorLog("Failed to fetch module modes: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    /**
     * Get courses/papers/mock exams filtered by mode and academic year.
     *
     * @param GetFilteredItemsRequest $request
     * @return JsonResponse
     */
    public function getFilteredItems(GetFilteredItemsRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $moduleId = $validated['module_id'];
            $academicYearId = $validated['academic_year_id'] ?? null;

            $mode = Module::find($moduleId);
            if (!$mode) {
                return sendError('Invalid mode ID.', [], 422);
            }

            $items = [];

            // Determine mode type by name
            $modeName = strtolower($mode->name);
            switch ($modeName) {
                case 'courses':
                    $query = Course::where('status', config('constants.statuses.APPROVED'));

                    if ($academicYearId) {
                        $query->whereHas('acdemicyears', function ($q) use ($academicYearId) {
                            $q->where('acdemic_years.id', $academicYearId);
                        });
                    }

                    $items = $query->select('id', 'name')
                        ->orderBy('name', 'asc')
                        ->get()
                        ->map(function ($course) {
                            return [
                                'id' => $course->id,
                                'name' => $course->name,
                            ];
                        });
                    break;

                case 'papers':
                    $items = Paper::where('status', config('constants.statuses.APPROVED'))
                        ->select('id', 'name')
                        ->orderBy('name', 'asc')
                        ->get()
                        ->map(function ($paper) {
                            return [
                                'id' => $paper->id,
                                'name' => $paper->name,
                            ];
                        });
                    break;

                case 'mock exams':
                    $items = MockExam::where('status', config('constants.statuses.APPROVED'))
                        ->select('id', 'name')
                        ->orderBy('name', 'asc')
                        ->get()
                        ->map(function ($exam) {
                            return [
                                'id' => $exam->id,
                                'name' => $exam->name,
                            ];
                        });
                    break;

                default:
                    return sendError('Invalid mode. Must be one of: courses, papers, mock_exams', [], 422);
            }

            return sendResponse($items, ucfirst($mode->name) . ' fetched successfully.', 200);
        } catch (\Exception $e) {
            return errorLog("Failed to fetch filtered items: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    /**
     * Get classes (timeslots) for a specific course and academic year.
     * Fetches class names from course_time_slots table based on course_id and academic_year_id.
     *
     * @param GetClassesRequest $request
     * @return JsonResponse
     */
    public function getClasses(GetClassesRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $courseId = $validated['course_id'];
            $academicYearId = $validated['academic_year_id'];

            // First, find the academic_course_id by matching course_id and academic_year_id
            $academicCourse = AcdemicCourse::where('course_id', $courseId)
                ->where('acdemic_id', $academicYearId)
                ->whereNull('deleted_at')
                ->first();

            if (!$academicCourse) {
                return sendResponse([], 'No academic course found for the selected course and academic year.', 200);
            }

            // Fetch classes from course_time_slots where course_id and academic_course_id match
            $classes = CourseTimeSlot::where('course_id', $courseId)
                ->where('academic_course_id', $academicCourse->id)
                ->whereNull('deleted_at')
                ->select('id', 'class_name')
                ->distinct()
                ->orderBy('class_name', 'asc')
                ->get()
                ->map(function ($slot) {
                    return [
                        'id' => $slot->id,
                        'name' => $slot->class_name,
                    ];
                });

            return sendResponse($classes, 'Classes fetched successfully.', 200);
        } catch (\Exception $e) {
            return errorLog("Failed to fetch classes: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    /**
     * Send announcement notifications to all users with the target roles
     *
     * @param Announcement $announcement
     * @return void
     */
    protected function sendAnnouncementNotifications(Announcement $announcement): void
    {
        try {
            // Reload announcement with roles to ensure we have the latest data
            $announcement->load('roles:id,name');

            // Get all role IDs from the announcement
            $roleIds = $announcement->roles->pluck('id')->toArray();

            if (empty($roleIds)) {
                return;
            }

            // Get all users who have any of the target roles
            $users = User::whereHas('roles', function ($query) use ($roleIds) {
                $query->whereIn('roles.id', $roleIds);
            })->whereNull('deleted_at') // Exclude soft-deleted users
                ->get();

            $successCount = 0;
            $failureCount = 0;

            // Send notification to each user
            foreach ($users as $user) {
                try {
                    $user->notify(new AnnouncementNotification($announcement));
                    $successCount++;
                } catch (\Exception $e) {
                    // Log individual notification failures but continue
                    errorLog("Failed to send notification to user {$user->id}: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
                    $failureCount++;
                }
            }
        } catch (\Exception $e) {
            // Log error but don't fail the announcement creation/update
            errorLog("Failed to send announcement notifications: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }
}
