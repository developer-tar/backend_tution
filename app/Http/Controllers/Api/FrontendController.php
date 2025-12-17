<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SlugValidateRequest;
use App\Models\Announcement;
use App\Models\Course;
use App\Models\CourseTimeSlot;
use App\Models\MockExam;
use App\Models\MockExamCategory;
use App\Models\Mode;
use App\Models\Paper;
use App\Models\StudentDetail;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class FrontendController extends Controller
{
    public function courseView()
    {
        try {
            $courses = $this->fetchCourses();

            return response()->json([
                'success' => true,
                'message' => 'Courses fetched successfully.',
                'data' => $courses,
            ]);
        } catch (Exception $e) {
            Log::error("Failed to fetch courses: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
            return sendError('error', ['error' => 'An error occurred while fetching courses.'], 500);
        }
    }

    public function courseViewBySlug(SlugValidateRequest $request)
    {
        try {
            $courses = $this->fetchCourses(['slug' => $request->input('slug')], false);

            return response()->json([
                'success' => true,
                'message' => 'Course fetched successfully.',
                'data' => $courses,
            ]);
        } catch (Exception $e) {
            Log::error("Failed to fetch course by slug: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
            return sendError('error', ['error' => 'An error occurred while fetching course.'], 500);
        }
    }

    /**
     * Shared method to fetch course(s) with optional filters and pagination.
     *
     * @param array $filters
     * @param bool $paginate
     * @return mixed
     */
    private function fetchCourses(array $filters = [], bool $paginate = true)
    {
        $query = Course::with([
            'subjects:id,name',
            'locations:id,name',
            'modes:id,name',
            'features:id,name,course_id',
            'acdemicyears.weeks',
            'prices:id,course_id,amount,billing_period_id,currency,stripe_price_id,mode_id',
            'prices.billingPeriod:id,name',
            'prices.mode:id,name',
            'slots:id,course_id,location_id,start_time,end_time,weekday_id,class_name,remaining_seats',
            'slots.locations:id,name',
            'slots.weekDays:id,name',
            'modefeatures:id,course_id,online_features_names,in_person_features_names',
        ])->where('status', config('constants.statuses.APPROVED'));

        foreach ($filters as $key => $value) {
            $query->where($key, $value);
        }

        return $paginate
            ? $query->latest()->paginate(10)->through(fn($course) => $this->transformCourseData($course, true))
            : $query->get()->transform(fn($course) => $this->transformCourseData($course));
    }

    /**
     * Transform a single course to API response format.
     *
     * @param \App\Models\Course $course
     * @param bool $limitDescription
     * @param bool $paginate
     * @return array
     */
    private function transformCourseData($course, bool $limitDescription = false): array
    {
        $academicYear = $course->acdemicyears->first();
        $weeks = $academicYear?->weeks ?? collect();
        $firstWeekStart = $weeks->first()?->start_date;
        $lastWeekEnd = $weeks->last()?->end_date;

        $startEndDate = $firstWeekStart && $lastWeekEnd
            ? \Carbon\Carbon::parse($firstWeekStart)->format('d M Y') . ' to ' . \Carbon\Carbon::parse($lastWeekEnd)->format('d M Y')
            : null;

        $withoutPagination = [];
        if ($limitDescription === false) {

            $withoutPagination = [
                'online_mode_features' => $course->modefeatures
                    ->pluck('online_features_names')
                    ->filter()
                    ->unique()
                    ->values()
                    ->toArray(),
                'in_person_mode_features' => $course->modefeatures
                    ->pluck('in_person_features_names')
                    ->filter()
                    ->unique()
                    ->values()
                    ->toArray(),
                'start_end_date' => $startEndDate,

                'price_according_to_mode' => collect($course->prices)
                    ->filter(function ($price) {
                        return $price->mode && $price->billingPeriod;
                    })
                    ->groupBy(function ($price) {
                        return $price->mode->name;
                    })
                    ->map(function ($pricesByMode) {
                        return $pricesByMode->mapWithKeys(function ($price) {
                            $key = $price->billingPeriod->name;

                            return [
                                $key => [
                                    'price' => $price->currency . '' . (float) $price->amount,
                                    'price_id' => $price->stripe_price_id,
                                ],
                            ];
                        });
                    }),
                'features' => $course->features?->pluck('name') ?? [],
                'weeks_count' => $weeks->count(),
                'locations' => $course->locations->map(function ($location) use ($course) {
                    $locationSlots = $course->slots
                        ->where('location_id', $location->id)
                        ->map(function ($slot) {
                            return [
                                'class' => $slot->class_name,
                                'weekday' => $slot->weekDays?->name ?? null,
                                'start_end_time' => $slot->start_time . ' - ' . $slot->end_time,
                                'seat_left' => $slot->remaining_seats ?? 0,
                            ];
                        })
                        ->values();

                    return [
                        'name' => $location->name,
                        'slots' => $locationSlots,
                    ];
                }),
            ];
        }
        if ($course->getFirstMediaUrl('course_image') == "") {
            $image = config('constants.dummy_image');
        } else {
            $image = $course->getFirstMediaUrl('course_image');
        }
        $existingArray = [
            'id' => $course->id,
            'acdemicyear' => $academicYear?->start_end_year,
            'name' => $course->name,
            'slug' => $course->slug,
            'subjects' => $course->subjects->pluck('name'),
            'modes' => $course->modes->pluck('name'),
            'image' => $image,
            'description' => $limitDescription
                ? Str::limit($course->description, 50)
                : ($course->description ?? null),
        ];

        return array_merge($existingArray, $withoutPagination);
    }

    /**
     * Get all mock exams for public view
     */
    public function mockExamView(Request $request)
    {
        try {
            $categoryId = $request->input('category_id');
            $formatId = $request->input('format_id');

            $mockExams = MockExam::with([
                'category:id,name',
                'format:id,name',
                'school:id,name',
            ])
                ->where('status', config('constants.statuses.APPROVED'))
                ->when($categoryId, fn($q) => $q->where('category_id', $categoryId))
                ->when($formatId, fn($q) => $q->where('format_id', $formatId))
                ->latest()
                ->paginate(12)
                ->through(function ($exam) {
                    return [
                        'id' => $exam->id,
                        'name' => $exam->name,
                        'description' => Str::limit($exam->description, 100),
                        'category' => $exam->category?->name,
                        'format' => $exam->format?->name,
                        'price' => $exam->currency . $exam->price,
                        'duration_minutes' => $exam->duration_minutes,
                        'total_marks' => $exam->total_marks,
                        'school' => $exam->school?->name,
                        'image' => $exam->getFirstMediaUrl('mock_exam_image') ?: config('constants.dummy_image'),
                        'stripe_price_id' => $exam->stripe_price_id,
                        'slug' => $exam->slug,
                    ];
                });
            return sendResponse($mockExams, 'Mock exams fetched successfully.');
        } catch (Exception $e) {
            return errorLog("Failed to fetch mock exams: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    /**
     * Get single mock exam details for public view
     */
    public function mockExamDetails($slug)
    {
        try {
            $mockExam = MockExam::with([
                'category:id,name',
                'format:id,name',
                'school:id,name',
                'questions:id,mock_exam_id',
            ])
                ->where('status', config('constants.statuses.APPROVED'))
                ->where('slug', $slug)
                ->first();

            if (!$mockExam) {
                return sendError('Mock exam not found', [], 404);
            }

            $data = [
                'id' => $mockExam->id,
                'name' => $mockExam->name,
                'description' => $mockExam->description,
                'category' => $mockExam->category?->name,
                'format' => $mockExam->format?->name,
                'price' => $mockExam->price,
                'currency' => $mockExam->currency,
                'formatted_price' => $mockExam->currency . $mockExam->price,
                'duration_minutes' => $mockExam->duration_minutes,
                'total_marks' => $mockExam->total_marks,
                'questions_count' => $mockExam->questions->count(),
                'school' => $mockExam->school?->name,
                'image' => $mockExam->getFirstMediaUrl('mock_exam_image') ?: config('constants.dummy_image'),
                'stripe_price_id' => $mockExam->stripe_price_id,
            ];
            return sendResponse($data, 'Mock exam details fetched successfully.');
        } catch (Exception $e) {
            return errorLog("Failed to fetch mock exam details: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    /**
     * Get mock exam categories for filters
     */
    public function mockExamCategories()
    {
        try {
            $categories = MockExamCategory::whereNull('parent_id')
                ->with('allChildren')
                ->orderBy('name')
                ->get();
            return sendResponse($categories, 'Categories fetched successfully.');
        } catch (Exception $e) {
            return errorLog("Failed to fetch categories: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    public function paperView(Request $request)
    {
        try {
            $categoryId = $request->input('category_id');
            $formatId = $request->input('format_id');

            $papers = Paper::with([
                'category:id,name',
                'format:id,name',
                'school:id,name',
            ])
                ->where('status', config('constants.statuses.APPROVED'))
                ->when($categoryId, fn($q) => $q->where('category_id', $categoryId))
                ->when($formatId, fn($q) => $q->where('format_id', $formatId))
                ->latest()
                ->paginate(12)
                ->through(function ($paper) {
                    $tableMap = config('constants.table_map');
                    $productTypeKey = array_search('papers', $tableMap) ?: 'papers';

                    return [
                        'id' => $paper->id,
                        'name' => $paper->name,
                        'description' => Str::limit($paper->description, 100),
                        'category' => $paper->category?->name,
                        'format' => $paper->format->name,
                        'price' => $paper->price,
                        'currency' => $paper->currency,
                        'duration_minutes' => $paper->duration_minutes,
                        'total_marks' => $paper->total_marks,
                        'school' => $paper->school?->name,
                        'slug' => $paper->slug,
                        'image' => $paper->getFirstMediaUrl('paper_image') ?: config('constants.dummy_image'),
                        'stripe_product_id' => $paper->stripe_product_id,
                        'stripe_price_id' => $paper->stripe_price_id,
                        'product_type' => $productTypeKey,
                    ];
                });

            return sendResponse($papers, 'Papers fetched successfully.');
        } catch (Exception $e) {
            return errorLog("Failed to fetch papers: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    public function paperDetails($slug)
    {
        try {
            $paper = Paper::with([
                'category:id,name',
                'format:id,name',
                'school:id,name',
                'questions:id,paper_id',
            ])
                ->where('status', config('constants.statuses.APPROVED'))
                ->where('slug', $slug)
                ->first();

            if (!$paper) {
                return sendError('Paper not found', [], 404);
            }

            // Get PDF count from media collection
            $pdfs = $paper->getMedia('paper_pdfs');
            $pdfsCount = $pdfs->count();

            $tableMap = config('constants.table_map');
            $productTypeKey = array_search('papers', $tableMap) ?: 'papers';

            $data = [
                'id' => $paper->id,
                'name' => $paper->name,
                'description' => $paper->description,
                'category' => $paper->category?->name,
                'format' => $paper->format->name,
                'price' => $paper->price,
                'currency' => $paper->currency,
                'duration_minutes' => $paper->duration_minutes,
                'total_marks' => $paper->total_marks,
                'school' => $paper->school?->name,
                'questions_count' => $paper->questions->count(),
                'pdfs_count' => $pdfsCount,
                'slug' => $paper->slug,
                'image' => $paper->getFirstMediaUrl('paper_image') ?: config('constants.dummy_image'),
                'stripe_product_id' => $paper->stripe_product_id,
                'stripe_price_id' => $paper->stripe_price_id,
                'product_type' => $productTypeKey,
            ];

            return sendResponse($data, 'Paper details fetched successfully.');
        } catch (Exception $e) {
            return errorLog("Failed to fetch paper details: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    public function paperCategories()
    {
        try {
            // Papers use the same categories as mock exams
            $categories = MockExamCategory::whereNull('parent_id')
                ->with('allChildren')
                ->orderBy('name')
                ->get();
            return sendResponse($categories, 'Categories fetched successfully.');
        } catch (Exception $e) {
            return errorLog("Failed to fetch categories: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    /**
     * Get all published announcements for public view
     * Filters by role (Admin, Student, Parent) and grade/year for students
     */
    public function announcementView(Request $request)
    {
        try {
            $user = Auth::user();
            $userRoles = $user ? $user->roles->pluck('name')->toArray() : [];
            $userYearIds = [];

            // Get year IDs based on user role
            if ($user) {
                if (in_array(config('constants.roles.STUDENT'), $userRoles)) {
                    // For students: get their year_id
                    $studentDetail = StudentDetail::where('child_id', $user->id)->first();
                    if ($studentDetail && $studentDetail->year_id) {
                        $userYearIds[] = $studentDetail->year_id;
                    }
                } elseif (in_array(config('constants.roles.PARENT'), $userRoles)) {
                    // For parents: get all their students' year_ids
                    $studentDetails = StudentDetail::where('parent_id', $user->id)
                        ->whereNotNull('year_id')
                        ->pluck('year_id')
                        ->unique()
                        ->toArray();
                    $userYearIds = array_values($studentDetails);
                }
                // For admins: they see all announcements, so no year filtering needed
            }

            $query = Announcement::with('creator:id,first_name,last_name,email')
                ->published()
                ->orderBy('is_pinned', 'desc')
                ->orderBy('created_at', 'desc');

            // Filter by type if provided
            if ($request->filled('type')) {
                $query->where('type', $request->type);
            }

            // Filter announcements based on target roles (Admin, Student, Parent)
            if (!empty($userRoles)) {
                $query->where(function ($q) use ($userRoles) {
                    $q->whereNull('target_roles');
                    foreach ($userRoles as $role) {
                        $q->orWhereJsonContains('target_roles', $role);
                    }
                });
            } else {
                // If user is not authenticated, show only announcements with no target roles
                $query->where(function ($q) {
                    $q->whereNull('target_roles')
                        ->orWhereJsonLength('target_roles', 0);
                });
            }

            // Filter by target years (grades) - only for students and parents
            if (!empty($userYearIds) && (in_array(config('constants.roles.STUDENT'), $userRoles) || in_array(config('constants.roles.PARENT'), $userRoles))) {
                $query->where(function ($q) use ($userYearIds) {
                    // Show announcements with no year restriction OR announcements matching user's year(s)
                    $q->whereNull('target_years')
                        ->orWhereJsonLength('target_years', 0);
                    foreach ($userYearIds as $yearId) {
                        $q->orWhereJsonContains('target_years', (int)$yearId);
                    }
                });
            } elseif (empty($userRoles) || !in_array(config('constants.roles.ADMIN'), $userRoles)) {
                // For non-authenticated users or non-admin users without year data, show only announcements with no year restriction
                $query->where(function ($q) {
                    $q->whereNull('target_years')
                        ->orWhereJsonLength('target_years', 0);
                });
            }
            // Admins see all announcements regardless of year restrictions

            $perPage = $request->integer('per_page', 10);
            $perPage = min($perPage, 50);

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

                return [
                    'id' => $announcement->id,
                    'title' => $announcement->title,
                    'description' => $announcement->description,
                    'content' => $announcement->content,
                    'type' => $announcement->type,
                    'is_pinned' => $announcement->is_pinned,
                    'published_at' => $announcement->published_at?->format('Y-m-d H:i:s'),
                    'expires_at' => $announcement->expires_at?->format('Y-m-d H:i:s'),
                    'target_roles' => $announcement->target_roles,
                    'target_years' => $announcement->target_years,
                    'image' => $announcement->getFirstMediaUrl('announcement_image') ?: null, // First image for backward compatibility
                    'images' => $allImages, // All images
                    'images_count' => count($allImages),
                    'created_by' => $announcement->creator ? [
                        'id' => $announcement->creator->id,
                        'name' => $announcement->creator->full_name,
                        'email' => $announcement->creator->email,
                    ] : null,
                    'created_at' => $announcement->created_at->format('Y-m-d H:i:s'),
                ];
            });

            return sendResponse($announcements, 'Announcements fetched successfully.');
        } catch (Exception $e) {
            return errorLog("Failed to fetch announcements: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    /**
     * Get a single announcement by ID for public view
     * Checks access based on role and year (grade)
     */
    public function announcementDetails($id)
    {
        try {
            $user = Auth::user();
            $userRoles = $user ? $user->roles->pluck('name')->toArray() : [];
            $userYearIds = [];

            // Get year IDs based on user role
            if ($user) {
                if (in_array(config('constants.roles.STUDENT'), $userRoles)) {
                    // For students: get their year_id
                    $studentDetail = StudentDetail::where('child_id', $user->id)->first();
                    if ($studentDetail && $studentDetail->year_id) {
                        $userYearIds[] = $studentDetail->year_id;
                    }
                } elseif (in_array(config('constants.roles.PARENT'), $userRoles)) {
                    // For parents: get all their students' year_ids
                    $studentDetails = StudentDetail::where('parent_id', $user->id)
                        ->whereNotNull('year_id')
                        ->pluck('year_id')
                        ->unique()
                        ->toArray();
                    $userYearIds = array_values($studentDetails);
                }
            }

            $announcement = Announcement::with('creator:id,first_name,last_name,email')
                ->published()
                ->find($id);

            if (!$announcement) {
                return sendError('Announcement not found', [], 404);
            }

            // Check if user has access based on target roles
            if (!empty($announcement->target_roles)) {
                if (empty($userRoles) || !array_intersect($announcement->target_roles, $userRoles)) {
                    return sendError('You do not have access to this announcement', [], 403);
                }
            }

            // Check if user has access based on target years (grades)
            // Admins can see all announcements regardless of year
            if (!empty($announcement->target_years) && !in_array(config('constants.roles.ADMIN'), $userRoles)) {
                // For students and parents, check if their year matches
                if (empty($userYearIds) || !array_intersect($announcement->target_years, $userYearIds)) {
                    return sendError('You do not have access to this announcement based on your grade/year', [], 403);
                }
            }

            $data = [
                'id' => $announcement->id,
                'title' => $announcement->title,
                'description' => $announcement->description,
                'content' => $announcement->content,
                'type' => $announcement->type,
                'is_pinned' => $announcement->is_pinned,
                'published_at' => $announcement->published_at?->format('Y-m-d H:i:s'),
                'expires_at' => $announcement->expires_at?->format('Y-m-d H:i:s'),
                'target_roles' => $announcement->target_roles,
                'target_years' => $announcement->target_years,
                'image' => $announcement->getFirstMediaUrl('announcement_image') ?: null,
                'images' => $announcement->getMedia('announcement_image')->map(function ($media) {
                    return [
                        'id' => $media->id,
                        'url' => $media->getUrl(),
                        'name' => $media->name,
                        'size' => $media->size,
                        'mime_type' => $media->mime_type,
                    ];
                })->toArray(),
                'images_count' => $announcement->getMedia('announcement_image')->count(),
                'created_by' => $announcement->creator ? [
                    'id' => $announcement->creator->id,
                    'name' => $announcement->creator->full_name,
                    'email' => $announcement->creator->email,
                ] : null,
                'created_at' => $announcement->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $announcement->updated_at->format('Y-m-d H:i:s'),
            ];

            return sendResponse($data, 'Announcement details fetched successfully.');
        } catch (Exception $e) {
            return errorLog("Failed to fetch announcement details: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    /**
     * Get all active classes (unique class names from active course time slots)
     */
    public function getActiveClasses()
    {
        try {
            $classes = CourseTimeSlot::whereHas('courses', function ($query) {
                $query->where('status', config('constants.statuses.APPROVED'));
            })
                ->whereNotNull('class_name')
                ->where('class_name', '!=', '')
                ->select('class_name')
                ->distinct()
                ->orderBy('class_name')
                ->pluck('class_name')
                ->unique()
                ->map(function ($className) {
                    return [
                        'name' => $className,
                    ];
                })
                ->values();

            return sendResponse($classes, 'Active classes fetched successfully.');
        } catch (Exception $e) {
            return errorLog("Failed to fetch active classes: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    /**
     * Get all active modes
     */
    public function getActiveModes()
    {
        try {
            $modes = Mode::select('id', 'name')
                ->orderBy('name')
                ->get()
                ->map(function ($mode) {
                    return [
                        'id' => $mode->id,
                        'name' => $mode->name,
                    ];
                });

            return sendResponse($modes, 'Active modes fetched successfully.');
        } catch (Exception $e) {
            return errorLog("Failed to fetch active modes: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }
}
