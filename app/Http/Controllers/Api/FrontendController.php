<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SlugValidateRequest;
use App\Models\Course;
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

                'price_according_to_mode' => collect($course->prices)->mapWithKeys(function ($price) {
                    $key = $price->billingPeriod->name;
                    $modeName = $price->mode?->name;
                    if ($modeName == null) {
                        return [];
                    }
                    return [
                        $modeName =>
                            [
                                $key => ['price' => $price->currency . '' . (float) $price->amount, 'price_id' => $price->stripe_price_id]
                            ]
                    ];
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
}
