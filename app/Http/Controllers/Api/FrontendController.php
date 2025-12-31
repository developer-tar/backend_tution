<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SlugValidateRequest;
use App\Models\Announcement;
use App\Models\Course;
use App\Models\MockExam;
use App\Models\MockExamCategory;
use App\Models\Paper;
use App\Models\Role;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
            return errorLog("Failed to fetch courses: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
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
            return errorLog("Failed to fetch course by slug: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
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
     * Get announcements for the authenticated user's dashboard
     * 
     * Logic:
     * 1. Get the logged-in user's role IDs
     * 2. Query announcement_role table to find announcement_ids where role_id matches user's role IDs
     * 3. Load announcement details from announcements table using those announcement_ids
     * 4. Only return published announcements (status = config('constants.announcement_status.publish') = 1)
     * 
     * Status values:
     * - draft = 0 (config('constants.announcement_status.draft'))
     * - publish = 1 (config('constants.announcement_status.publish'))
     * 
     * This ensures users only see announcements targeted to their specific role(s)
     */
    public function getAnnouncements(Request $request)
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return sendError('error', ['error' => 'Unauthorized.'], 401);
            }

            // Step 1: Get the logged-in user's role IDs
            try {
                $userRoles = $user->roles()->pluck('id')->toArray();
                $userRoleNames = $user->roles()->pluck('name')->toArray();
            } catch (\Exception $e) {
                errorLog("Error fetching user roles for user {$user->id}: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
                $userRoles = [];
                $userRoleNames = [];
            }

            if (empty($userRoles)) {
                return response()->json([
                    'success' => true,
                    'message' => 'No roles assigned to your account. Please contact administrator.',
                    'data' => [],
                    'debug' => [
                        'user_id' => $user->id,
                        'user_email' => $user->email,
                        'issue' => 'User has no roles assigned'
                    ]
                ], 200);
            }

            // Step 2: Query announcement_role table to find announcement_ids 
            // where role_id matches the logged-in user's role IDs
            try {
                $announcementIds = DB::table('announcement_role')
                    ->whereIn('role_id', $userRoles)
                    ->pluck('announcement_id')
                    ->unique()
                    ->toArray();

                // If no announcement IDs found, return empty array with debug info
                if (empty($announcementIds)) {
                    // Check if there are any announcements at all
                    $totalAnnouncements = DB::table('announcements')->whereNull('deleted_at')->count();
                    $publishedAnnouncements = DB::table('announcements')
                        ->where('status', config('constants.announcement_status.publish'))
                        ->whereNull('deleted_at')
                        ->count();

                    return response()->json([
                        'success' => true,
                        'message' => 'No announcements found for your role(s).',
                        'data' => [],
                        'debug' => [
                            'user_roles' => $userRoleNames,
                            'total_announcements_in_system' => $totalAnnouncements,
                            'published_announcements_in_system' => $publishedAnnouncements,
                            'issue' => 'No announcements are linked to your role(s) in the announcement_role table'
                        ]
                    ], 200);
                }

                // Step 3: Load announcement details from announcements table
                // using the announcement_ids found in announcement_role table
                // Only get published announcements (status = config('constants.announcement_status.publish') = 1)
                $announcements = Announcement::published()
                    ->whereIn('id', $announcementIds)
                    ->whereNull('deleted_at') // Exclude soft-deleted announcements
                    ->with('roles:id,name')
                    ->latest()
                    ->get();

                // Debug: Check if any announcements were filtered out due to status
                $allAnnouncements = Announcement::whereIn('id', $announcementIds)
                    ->whereNull('deleted_at')
                    ->get();
            } catch (\Exception $e) {
                errorLog("Error querying announcements for user {$user->id}: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
                throw $e; // Re-throw to be caught by outer catch
            }

            // Transform the data for frontend
            $transformedAnnouncements = $announcements->map(function ($announcement) {
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
                    'created_at' => $announcement->created_at->format('Y-m-d H:i:s'),
                    'created_at_human' => $announcement->created_at->diffForHumans(),
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Announcements fetched successfully.',
                'data' => $transformedAnnouncements,
            ], 200);
        } catch (\Exception $e) {
            return errorLog("Failed to fetch announcements: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }
}
