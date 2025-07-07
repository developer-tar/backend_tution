<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SlugValidateRequest;
use App\Models\Course;
use Exception;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class FrontendController extends Controller {

    public function courseView() {
        try {
            $courses = Course::with('subjects:id,name', 'locations:id,name', 'acdemicyears', 'acdemicyears.weeks')
                ->where('status', config('constants.statuses.APPROVED'))
                ->orderBy('created_at', 'desc')
                ->paginate(10)
                ->through(function ($course) {
                    $academicYear = $course->acdemicyears->first();
                    $weeks = $academicYear?->weeks ?? collect();

                    $weeksCount = $weeks->count();
                    $firstWeekStart = $weeks->first()?->start_date;
                    $lastWeekEnd = $weeks->last()?->end_date;
                    $startEndDate = $firstWeekStart && $lastWeekEnd
                        ? \Carbon\Carbon::parse($firstWeekStart)->format('d M Y') . ' to ' . \Carbon\Carbon::parse($lastWeekEnd)->format('d M Y')
                        : null;

                    return [
                        'acdemicyear' => $course?->acdemicyears->first() ? $course?->acdemicyears->first()->start_end_year : null,
                        'name' => $course->name,
                        'slug' => $course->slug,
                        'subjects' => $course->subjects->pluck('name'),
                        'locations' => $course->locations->pluck('name'),
                        'modes' => $course->modes->pluck('name'),
                        'image' => $course->getFirstMediaUrl('course_image') ?? null,
                        'price' => $course->amount,
                        'description' => $course->description ? Str::limit($course->description, 50) : null,
                        'weeks_count' => $weeksCount,
                        'start_end_date' => $startEndDate,
                    ];
                });
            $response = [
                'success' => true,
                'message' => 'Courses fetched successfully.',
                'data' => $courses,

            ];
            return response()->json($response, 200);
        } catch (Exception $e) {

            Log::error("Failed to fetch courses. Message => {$e->getMessage()}, File => {$e->getFile()}, Line => {$e->getLine()}, Code => {$e->getCode()}.");
            return sendError('error', ['error' => 'An error occurred while fetching courses.'], 500);
        }
    }

    public function courseViewBySlug(SlugValidateRequest $request) {
        try {

            $courseBySlug = Course::with([
                'subjects:id,name',
                'locations:id,name',
                'modes:id,name',
                'features:id,name,course_id',
                'acdemicyears.weeks'
            ])
                ->where('status', config('constants.statuses.APPROVED'))
                ->where('slug', $request->input('slug'))
                ->get()
                ->transform(function ($course) {
                    $academicYear = $course->acdemicyears->first();
                    $weeks = $academicYear?->weeks ?? collect();

                    $weeksCount = $weeks->count();
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
                        'features' => $course->features->pluck('name'),
                        'image' => $course->getFirstMediaUrl('course_image') ?? null,
                        'price' => $course->amount,
                        'description' => $course->description ?: null,
                        'weeks_count' => $weeksCount,
                        'start_end_date' => $startEndDate,
                    ];
                });

            $response = [
                'success' => true,
                'message' => 'Courses fetched successfully.',
                'data' => $courseBySlug,

            ];
            return response()->json($response, 200);
        } catch (Exception $e) {

            Log::error("Failed to fetch courses. Message => {$e->getMessage()}, File => {$e->getFile()}, Line => {$e->getLine()}, Code => {$e->getCode()}.");
            return sendError('error', ['error' => 'An error occurred while fetching courses.'], 500);
        }
    }
}
