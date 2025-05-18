<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;

use App\Http\Requests\StoreCourseRequest;
use App\Models\Course;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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

            $course['name'] = $request->name;
            $course['product_id'] = config('constants.product_id');
            $course['type_of_course'] = $request->type_of_course;
            $course['amount'] = $request->amount;



            $stripe = new \Stripe\StripeClient(config('constants.sk_test'));
            $price = $stripe->prices->create([
                'currency' => 'usd',
                'unit_amount' => $course['amount'] * 100,
                'product' => $course['product_id'],
            ]);//create the price in stripe 
            $course['price_id'] = $price->id;


            $courseObj = Course::create($course);

            if ($request->hasFile('course_image')) {
                $file = $request->file('course_image');//define the file 

                $path = $file->store('courses', 'public');//image upload local 

                $extension = $file->getClientOriginalExtension();//get the extension
                
                $course['course_image'] = 'storage/' . $path;//store the path 

                $courseObj->media()->create([
                    'path' => $course['course_image'],
                    'type' => getFileType($extension),
                    'model_name' => Course::class,
                ]);

            }


            $subjectIds = $request->subject_ids; // your subject IDs
            foreach ($subjectIds as $subjectId) {
                $courseObj->subjects()->attach($subjectId, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $locationIds = $request->location_ids; // your location IDs
            foreach ($locationIds as $locationId) {
                $courseObj->locations()->attach($locationId, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }


            $featureNames = $request->features_names; //your feature names
            foreach ($featureNames as $name) {
                $courseObj->features()->create(['created_id' => Auth::id(), 'course_id' => $courseObj['id'], 'name' => $name]);
            }

            $courseObj->acdemicyears()->attach($request->acdemic_year_id, [
                'created_at' => now(),
                'updated_at' => now(),
            ]);//create the acdemic years

            DB::commit();
            return sendResponse($user, 'Course created successfully.', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to show user. Message => {$e->getMessage()}, File => {$e->getFile()},  Line No => {$e->getLine()}, Error Code => {$e->getCode()}.");
            return sendError('error', ['error' => 'An error occurred during store.'], 500);
        }

    }
}
