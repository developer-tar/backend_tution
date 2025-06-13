<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SlugValidateRequest;
use App\Models\Course;
use Exception;
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

        return [
            'acdemicyear' => $academicYear?->start_end_year,
            'name' => $course->name,
            'slug' => $course->slug,
            'subjects' => $course->subjects->pluck('name'),
            'locations' => $course->locations->pluck('name'),
            'modes' => $course->modes->pluck('name'),
            'features' => $course->features?->pluck('name') ?? [],
            'image' => $course->getFirstMediaUrl('course_image') ?? null,
            'price' => $course->amount,
            'description' => $limitDescription
                ? Str::limit($course->description, 50)
                : ($course->description ?? null),
            'weeks_count' => $weeks->count(),
            'start_end_date' => $startEndDate,
        ];
    }
}
