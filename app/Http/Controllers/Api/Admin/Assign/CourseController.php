<?php

namespace App\Http\Controllers\Api\Admin\Assign;

use App\Http\Controllers\Controller;

use App\Http\Requests\Api\Admin\StoreCourseRequest;
use App\Jobs\CreateStripePrice;
use App\Models\Course;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
class CourseController extends Controller
{
    public function index()
    {
        try {
            $courses = Course::with('subjects:id,name', 'locations:id,name', 'modes:id,name', 'features:id,name,course_id', 'acdemicyears')
                ->where('created_id', Auth::id())
                ->orderBy('created_at', 'desc')
                ->paginate(10)
                ->through(function ($course) {
                    return [
                        'acdemicyear' => $course?->acdemicyears->first() ? $course?->acdemicyears->first()->start_end_year : null,
                        'name' => $course->name,
                        'slug' => $course->slug,
                        'subjects' => $course->subjects->pluck('name'),
                        'locations' => $course->locations->pluck('name'),
                        'modes' => $course->modes->pluck('name'),
                        'features' => $course->features->first()?->name ? Str::limit($course->features->first()?->name): null,
                        'image' => $course->getFirstMediaUrl('course_image') ?? null,
                        'amount' => $course->amount,
                        'description' => $course->description ? Str::limit($course->description, 50) : null,
                        'price_id' => $course?->price_id ?? null,
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
    /**
     * Store a newly created resource in storage.
     *
     * @param  StoreCourseRequest  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(StoreCourseRequest $request)
    {
        try {
            DB::beginTransaction();

            $courseData = [
                'name' => $request->name,
                'product_id' => config('services.stripe.product_id'),
                'type_of_course' => config('constants.course_type.WEEKLY'),
                'amount' => $request->amount,
                'created_id' => Auth::id(),
                'description' => $request->description,
                'slug' => Str::slug($request->name),
            ];

            $courseObj = Course::create($courseData);

            if ($request->hasFile('course_image')) {
                $courseObj->addMedia($request->file('course_image'))
                    ->toMediaCollection('course_image');
            }

            $subjectData = collect($request->subject_ids)->mapWithKeys(fn($id) => [
                $id => ['created_at' => now(), 'updated_at' => now()]
            ])->toArray();
            $courseObj->subjects()->attach($subjectData);

            $locationData = collect($request->location_ids)->mapWithKeys(fn($id) => [
                $id => ['created_at' => now(), 'updated_at' => now()]
            ])->toArray();
            $courseObj->locations()->attach($locationData);

            $modeData = collect($request->type_of_modes)->mapWithKeys(fn($id) => [
                $id => ['created_at' => now(), 'updated_at' => now()]
            ])->toArray();
            $courseObj->modes()->attach($modeData);

            foreach ($request->features_names as $name) {
                $courseObj->features()->create([
                    'created_id' => Auth::id(),
                    'course_id' => $courseObj->id,
                    'name' => $name
                ]);
            }

            $courseObj->acdemicyears()->attach($request->acdemic_year_id, [
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();

            // Dispatch stripe creation as a queued job
            dispatch(new CreateStripePrice($courseObj->id, $courseObj->amount));

            return sendResponse('Course created successfully.', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to create course. Message => {$e->getMessage()}, File => {$e->getFile()}, Line => {$e->getLine()}, Code => {$e->getCode()}.");
            return sendError('error', ['error' => 'An error occurred during store.'], 500);
        }


    }
}
