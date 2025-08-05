<?php

namespace App\Http\Controllers\Api\Admin\Assign;

use App\Http\Controllers\Controller;

use App\Http\Requests\Api\Admin\StoreCourseRequest;
use App\Jobs\CreateStripePrice;
use App\Jobs\UploadCourseImageJob;
use App\Models\BillingPeriod;
use App\Models\Course;
use App\Models\Mode;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CourseController extends Controller {
    public function index() {
        try {
            $courses = Course::with('subjects:id,name', 'modes:id,name', 'features:id,name,course_id', 'acdemicyears', 'prices:id,course_id,amount,billing_period_id,currency', 'prices.billingPeriod:id,name', 'slots:id,course_id,location_id,start_time,end_time,weekday_id,seats,class_name,remaining_seats', 'slots.locations:id,name', 'slots.weekDays:id,name')
                ->where('created_id', Auth::id())
                ->orderBy('created_at', 'desc')
                ->paginate(10)
                ->through(function ($course) {
                    return [
                        'acdemicyear' => $course?->acdemicyears->first()?->start_end_year ?? null,
                        'name' => $course->name,
                        'slug' => $course->slug,
                        'subjects' => $course->subjects->pluck('name'),
                        'locations' => $course->locations->map(function ($location) use ($course) {
                            $locationSlots = $course->slots
                                ->where('location_id', $location->id)
                                ->map(function ($slot) {
                                    return [
                                        'class' => $slot->class_name,
                                        'weekday' => $slot->weekDays?->name ?? null,
                                        'start_end_time' => $slot->start_time . ' - ' . $slot->end_time,
                                        'max_seats' => $slot->seats,
                                        'seat_left' => $slot->remaining_seats,
                                    ];
                                })
                                ->values();

                            return [
                                'name' => $location->name,
                                'slots' => $locationSlots,
                            ];
                        }),
                        'modes' => $course->modes->pluck('name'),
                        'features' => optional($course->features->first())->name
                            ? Str::limit($course->features->first()->name)
                            : null,
                        'image' => $course->getFirstMediaUrl('course_image') ?? null,
                        'description' => $course->description ? Str::limit($course->description, 50) : null,
                        'price_id' => $course->price_id ?? null,
                        'amounts' => collect($course->prices)->mapWithKeys(function ($price) {
                            $key = $price->billingPeriod->name;
                            return [$key => $price->currency . (float) $price->amount];
                        }),
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
    public function store(StoreCourseRequest $request) {
        try {

            DB::beginTransaction();

            $courseData = [
                'name' => $request->name,
                'product_id' => config('services.stripe.product_id'),
                'type_of_course' => config('constants.course_type.WEEKLY'),
                'created_id' => Auth::id(),
                'description' => $request->description,
                'slug' => Str::slug($request->name),
            ];

            $data = $request->validated();

            $courseObj = Course::create($courseData);

            BillingPeriod::all()->each(function ($billingPeriod) use ($courseObj, $data) {
                $billingId = $billingPeriod->id;
                if(isset($data["amount_for_online_{$billingId}"])) {
                    $onlineRecord = Mode::where(['id' => $data['type_of_modes']])
                        ->where('name', 'Online')
                        ->value('id');
                    $coursePriceData = [
                        'course_id' => $courseObj->id,
                        'billing_period_id' => $billingId,
                        'amount' => $data["amount_for_online_{$billingId}"],
                         'mode_id' => $onlineRecord,
                    ];
                    $courseObj->prices()->create($coursePriceData);
                }
                if(isset($data["amount_for_online_{$billingId}"])) {
                    $inPersonRecord = Mode::where(['id' => $data['type_of_modes']])
                        ->where('name', 'In person')
                        ->value('id');
                    $coursePriceData = [
                        'course_id' => $courseObj->id,
                        'billing_period_id' => $billingId,
                        'amount' => $data["amount_for_online_{$billingId}"],
                         'mode_id' => $inPersonRecord,
                    ];
                    $courseObj->prices()->create($coursePriceData);
                }
                // if (isset($data["amount_{$billingId}"])) {
                //     $coursePriceData = [
                //         'course_id' => $courseObj->id,
                //         'billing_period_id' => $billingId,
                //         'amount' => $data["amount_{$billingId}"],
                //     ];
                //     $courseObj->prices()->create($coursePriceData);
                // }
            }); //create course prices for each billing period

            // if ($request->hasFile('course_image')) {
            //     UploadCourseImageJob::dispatch($courseObj, $request->file('course_image'));
            // }

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
            // dispatch(new CreateStripePrice($courseObj->id, $courseObj->amount));
            dispatch(new CreateStripePrice($courseObj->id));
            return sendResponse('Course created successfully.', 201);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Failed to create course. Message => {$e->getMessage()}, File => {$e->getFile()}, Line => {$e->getLine()}, Code => {$e->getCode()}.");
            return sendError('error', ['error' => 'An error occurred during store.'], 500);
        }
    }
}
