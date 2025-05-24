<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;

use App\Http\Requests\Api\Admin\StoreCourseRequest;
use App\Jobs\CreateStripePrice;
use App\Models\Course;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CourseController extends Controller
{
    public function index()
    {

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
                'type_of_course' => 1,
                'amount' => $request->amount,
                'created_id' => Auth::id(),
                'description' => $request->description,
            ];

            $courseObj = Course::create($courseData);

            if ($request->hasFile('course_image')) {
                $courseObj->addMediaFromRequest('course_image')
                    ->toMediaCollection('course_image', 'public');
            }

            $subjectData = collect($request->subject_ids)->mapWithKeys(fn($id) => [
                $id => ['created_at' => now(), 'updated_at' => now()]
            ])->toArray();
            $courseObj->subjects()->attach($subjectData);

            $locationData = collect($request->location_ids)->mapWithKeys(fn($id) => [
                $id => ['created_at' => now(), 'updated_at' => now()]
            ])->toArray();
            $courseObj->locations()->attach($locationData);

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
