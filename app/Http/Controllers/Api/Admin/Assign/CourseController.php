<?php

namespace App\Http\Controllers\Api\Admin\Assign;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\Api\Admin\StoreCourseRequest;
use App\Http\Requests\Api\Admin\UpdateCourseRequest;
use App\Http\Requests\Api\Admin\DeleteCourseRequest;
use App\Http\Requests\Api\Admin\ToggleCourseStatusRequest;
use App\Jobs\CreateStripePrice;
use App\Jobs\UpdateStripePrice;
use App\Jobs\DeleteStripeProduct;
use App\Jobs\UploadCourseImageJob;
use App\Models\BillingPeriod;
use App\Models\Course;
use App\Models\Mode;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CourseController extends Controller
{
   
    public function index(Request $request)
    {
        try {
            $search = $request->input('search');
            
            $courses = Course::with('subjects:id,name', 'modes:id,name', 'features:id,name,course_id', 'acdemicyears', 'prices:id,course_id,amount,billing_period_id,currency', 'prices.billingPeriod:id,name', 'slots:id,course_id,location_id,start_time,end_time,weekday_id,seats,class_name,remaining_seats', 'slots.locations:id,name', 'slots.weekDays:id,name')
                ->where('created_id', Auth::id())
                ->when($search, function ($query) use ($search) {
                    $query->where(function ($q) use ($search) {
                        // Search in Course fields
                        $q->where('name', 'LIKE', "%{$search}%")
                          ->orWhere('description', 'LIKE', "%{$search}%")
                          ->orWhere('slug', 'LIKE', "%{$search}%")
                          
                          // Search in Subjects
                          ->orWhereHas('subjects', function ($subjectQuery) use ($search) {
                              $subjectQuery->where('name', 'LIKE', "%{$search}%");
                          })
                          
                          // Search in Modes
                          ->orWhereHas('modes', function ($modeQuery) use ($search) {
                              $modeQuery->where('name', 'LIKE', "%{$search}%");
                          })
                          
                          // Search in Features
                          ->orWhereHas('features', function ($featureQuery) use ($search) {
                              $featureQuery->where('name', 'LIKE', "%{$search}%");
                          })
                          
                          // Search in Academic Years
                          ->orWhereHas('acdemicyears', function ($yearQuery) use ($search) {
                              $yearQuery->where('start_year', 'LIKE', "%{$search}%")
                                       ->orWhere('end_year', 'LIKE', "%{$search}%");
                          })
                          
                          // Search in Billing Periods through Prices
                          ->orWhereHas('prices.billingPeriod', function ($billingQuery) use ($search) {
                              $billingQuery->where('name', 'LIKE', "%{$search}%");
                          })
                          
                          // Search in Locations through Slots
                          ->orWhereHas('slots.locations', function ($locationQuery) use ($search) {
                              $locationQuery->where('name', 'LIKE', "%{$search}%");
                          })
                          
                          // Search in WeekDays through Slots
                          ->orWhereHas('slots.weekDays', function ($weekDayQuery) use ($search) {
                              $weekDayQuery->where('name', 'LIKE', "%{$search}%");
                          })
                          
                          // Search in Slots fields
                          ->orWhereHas('slots', function ($slotQuery) use ($search) {
                              $slotQuery->where('class_name', 'LIKE', "%{$search}%")
                                       ->orWhere('start_time', 'LIKE', "%{$search}%")
                                       ->orWhere('end_time', 'LIKE', "%{$search}%");
                          });
                    });
                })
                ->orderBy('created_at', 'desc')
                ->paginate(10)
                ->through(function ($course) {
                    // Map status to readable label
                    $statusMap = [
                        1 => 'pending',
                        2 => 'approved',
                        3 => 'rejected'
                    ];
                    $statusLabel = isset($statusMap[$course->status]) ? $statusMap[$course->status] : 'unknown';
                    
                    return [
                        'id' => $course->id,
                        'acdemicyear' => $course?->acdemicyears->first() 
                            ? ($course->acdemicyears->first()->start_year . '-' . $course->acdemicyears->first()->end_year)
                            : null,
                        'name' => $course->name,
                        'slug' => $course->slug,
                        'status' => $course->status,
                        'status_label' => $statusLabel,
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
                        
                        'amounts' => collect($course->prices)->mapWithKeys(function ($price) {
                            $key = $price->billingPeriod->name;
                            return [$key => $price->currency . (float) $price->amount];
                        }),
                    ];
                });
            $response = [
                'success' => true,
                'message' => $search 
                    ? ($courses->total() > 0 
                        ? "Courses found for search term '{$search}'." 
                        : "No courses found for search term '{$search}'.")
                    : 'Courses fetched successfully.',
                'data' => $courses,
                'search_term' => $search,
            ];
            return response()->json($response, 200);
        } catch (Exception $e) {
            Log::error("Failed to fetch courses. Message => {$e->getMessage()}, File => {$e->getFile()}, Line => {$e->getLine()}, Code => {$e->getCode()}.");
            return sendError('error', ['error' => 'An error occurred while fetching courses.'], 500);
        }
    }
    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $course = Course::with([
                'subjects:id,name',
                'modes:id,name',
                'locations:id,name',
                'features:id,name,course_id',
                'acdemicyears:id,start_year,end_year',
                'prices:id,course_id,amount,billing_period_id,mode_id,currency,stripe_product_id,stripe_price_id',
                'prices.billingPeriod:id,name,period',
                'prices.mode:id,name',
                'modefeatures:id,course_id,online_features_names,in_person_features_names'
            ])
            ->where('created_id', Auth::id())
            ->findOrFail($id);

            // Format prices by mode and billing period
            $prices = [];
            $onlineModeId = Mode::where('name', config('constants.modes.online'))->value('id');
            $inPersonModeId = Mode::where('name', config('constants.modes.in_person'))->value('id');

            foreach ($course->prices as $price) {
                $billingPeriodId = $price->billing_period_id;
                if ($price->mode_id == $onlineModeId) {
                    $prices["amount_for_online_{$billingPeriodId}"] = (float) $price->amount;
                } elseif ($price->mode_id == $inPersonModeId) {
                    $prices["amount_for_in_person_{$billingPeriodId}"] = (float) $price->amount;
                }
            }

            // Format mode features
            $onlineFeatures = [];
            $inPersonFeatures = [];
            foreach ($course->modefeatures as $modeFeature) {
                if (!empty($modeFeature->online_features_names)) {
                    $onlineFeatures[] = $modeFeature->online_features_names;
                }
                if (!empty($modeFeature->in_person_features_names)) {
                    $inPersonFeatures[] = $modeFeature->in_person_features_names;
                }
            }

            $data = [
                'id' => $course->id,
                'name' => $course->name,
                'slug' => $course->slug,
                'description' => $course->description,
                'image' => $course->getFirstMediaUrl('course_image') ?? null,
                'subject_ids' => $course->subjects->pluck('id')->toArray(),
                'type_of_modes' => $course->modes->pluck('id')->toArray(),
                'location_ids' => $course->locations->pluck('id')->toArray(),
                'features_names' => $course->features->pluck('name')->toArray(),
                'acdemic_year_id' => $course->acdemicyears->first()?->id,
                'online_features_names' => $onlineFeatures,
                'in_person_features_names' => $inPersonFeatures,
                'prices' => $prices,
            ];

            return response()->json([
                'success' => true,
                'message' => 'Course fetched successfully.',
                'data' => $data,
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return sendError('error', ['error' => 'Course not found.'], 404);
        } catch (Exception $e) {
            Log::error("Failed to fetch course. Message => {$e->getMessage()}, File => {$e->getFile()}, Line => {$e->getLine()}, Code => {$e->getCode()}.");
            return sendError('error', ['error' => 'An error occurred while fetching course.'], 500);
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
                'created_id' => Auth::id(),
                'description' => $request->description,
                'slug' => Str::slug($request->name),
            ];

            $data = $request->validated();

            $courseObj = Course::create($courseData);

            BillingPeriod::all()->each(function ($billingPeriod) use ($courseObj, $data) {
                $billingId = $billingPeriod->id;

                if (isset($data["amount_for_online_{$billingId}"])) {

                    $onlineRecord = Mode::where('name', config('constants.modes.online'))
                        ->value('id');//find out the id of online mode

                    //create the course mode feature for online    
                    $coursePriceData = [
                        'course_id' => $courseObj->id,
                        'billing_period_id' => $billingId,
                        'amount' => $data["amount_for_online_{$billingId}"],
                        'mode_id' => $onlineRecord,
                    ];
                    $courseObj->prices()->create($coursePriceData);
                }

                if (isset($data["amount_for_in_person_{$billingId}"])) {
                    $inPersonRecord = Mode::where('name', config('constants.modes.in_person'))
                        ->value('id');//find out the id of in person mode

                    //create the course mode feature for in person
                    $coursePriceData = [
                        'course_id' => $courseObj->id,
                        'billing_period_id' => $billingId,
                        'amount' => $data["amount_for_in_person_{$billingId}"],
                        'mode_id' => $inPersonRecord,
                    ];
                    $courseObj->prices()->create($coursePriceData);
                }

            }); //create course prices for each billing period
            if (isset($data['online_features_names']) && is_array($data['online_features_names'])) {
                //create the course mode feature for online
                foreach ($data['online_features_names'] as $name) {
                    $courseObj->modefeatures()->create([
                        'course_id' => $courseObj->id,
                        'online_features_names' => $name
                    ]);

                }//create the course mode feature for online
            }

            if (isset($data['in_person_features_names']) && is_array($data['in_person_features_names'])) {
                foreach ($data['in_person_features_names'] as $name) {
                    $courseObj->modefeatures()->create([
                        'course_id' => $courseObj->id,
                        'in_person_features_names' => $name
                    ]);
                }  //create the course mode feature for in person
            }
          
            if ($request->hasFile('course_image')) {
                // Use queue job for fast response and background R2 upload
                $file = $request->file('course_image');
                
                // Store file temporarily in local storage for queue processing
                $tempPath = $file->store('temp/course_images', 'local');
                
                // Dispatch job for background processing
                UploadCourseImageJob::dispatch(
                    $courseObj->id,
                    $tempPath,
                    $file->getClientOriginalName(),
                    $file->getMimeType()
                );
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
            // dispatch(new CreateStripePrice($courseObj->id, $courseObj->amount));
            dispatch(new CreateStripePrice($courseObj->id));
            return sendResponse('Course created successfully.', 201);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Failed to create course. Message => {$e->getMessage()}, File => {$e->getFile()}, Line => {$e->getLine()}, Code => {$e->getCode()}.");
            return sendError('error', ['error' => 'An error occurred during store.'], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  UpdateCourseRequest  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdateCourseRequest $request, $id)
    {
        try {
            Log::info('updated',['data' => $request->all()]);
            Log::info("=== COURSE UPDATE STARTED ===", [
                'course_id' => $id,
                'user_id' => Auth::id(),
                'request_method' => $request->method(),
                'has_file' => $request->hasFile('course_image')
            ]);
            
            DB::beginTransaction();
            Log::info("Transaction begun for course ID: {$id}");

            $courseObj = Course::where('created_id', Auth::id())->findOrFail($id);
            Log::info("Course found for update", [
                'course_id' => $courseObj->id,
                'current_name' => $courseObj->name,
                'current_description' => substr($courseObj->description ?? '', 0, 50) . '...'
            ]);
            
            // Get validated data
            $data = $request->validated();
            Log::info("Request validated successfully", [
                'validated_fields' => array_keys($data),
                'validated_data_count' => count($data)
            ]);

            // Update basic course fields
            $updateData = [];
            Log::info("Checking basic course fields for update");
            
            if (isset($data['name']) && !empty($data['name'])) {
                $updateData['name'] = $data['name'];
                $updateData['slug'] = Str::slug($data['name']);
                Log::info("Name field will be updated", [
                    'old_name' => $courseObj->name,
                    'new_name' => $data['name'],
                    'new_slug' => $updateData['slug']
                ]);
            }
            
            if (isset($data['description']) && !empty($data['description'])) {
                $updateData['description'] = $data['description'];
                Log::info("Description field will be updated", [
                    'old_description_length' => strlen($courseObj->description ?? ''),
                    'new_description_length' => strlen($data['description'])
                ]);
            }
            
            if (!empty($updateData)) {
                Log::info("Updating basic course fields", ['update_data' => $updateData]);
                $courseObj->update($updateData);
                Log::info("Basic course fields updated successfully", [
                    'updated_name' => $courseObj->fresh()->name,
                    'updated_slug' => $courseObj->fresh()->slug
                ]);
            } else {
                Log::info("No basic course fields to update");
            }

            // Update course prices
            Log::info("Checking for price updates");
            $billingPeriodIds = BillingPeriod::pluck('id')->toArray();
            Log::info("Billing period IDs found", ['billing_period_ids' => $billingPeriodIds]);
            
            $hasAnyPriceField = false;
            $priceFieldsToCheck = [];
            
            foreach ($billingPeriodIds as $billingId) {
                $onlineField = "amount_for_online_{$billingId}";
                $inPersonField = "amount_for_in_person_{$billingId}";
                
                $hasOnline = isset($data[$onlineField]) && !empty($data[$onlineField]);
                $hasInPerson = isset($data[$inPersonField]) && !empty($data[$inPersonField]);
                
                Log::info("Checking price fields for billing period {$billingId}", [
                    'online_field' => $onlineField,
                    'has_online' => $hasOnline,
                    'online_value' => $hasOnline ? $data[$onlineField] : null,
                    'in_person_field' => $inPersonField,
                    'has_in_person' => $hasInPerson,
                    'in_person_value' => $hasInPerson ? $data[$inPersonField] : null
                ]);
                
                if ($hasOnline) {
                    $hasAnyPriceField = true;
                    $priceFieldsToCheck[] = $onlineField;
                }
                if ($hasInPerson) {
                    $hasAnyPriceField = true;
                    $priceFieldsToCheck[] = $inPersonField;
                }
            }
            
            $hasTypeOfModes = isset($data['type_of_modes']) && !empty($data['type_of_modes']);
            Log::info("Price update decision", [
                'has_any_price_field' => $hasAnyPriceField,
                'has_type_of_modes' => $hasTypeOfModes,
                'price_fields_found' => $priceFieldsToCheck
            ]);
            
            // Also check if type_of_modes changed, which might require price updates
            $shouldUpdatePrices = $hasAnyPriceField || $hasTypeOfModes;
            
            if ($shouldUpdatePrices) {
                Log::info("Prices will be updated", [
                    'existing_prices_count' => $courseObj->prices()->count()
                ]);
                
                // Delete existing prices
                $deletedCount = $courseObj->prices()->delete();
                Log::info("Deleted existing prices", ['deleted_count' => $deletedCount]);

                // Create new prices based on request
                // If type_of_modes is provided, use it; otherwise reload and use existing modes
                if (isset($data['type_of_modes']) && !empty($data['type_of_modes'])) {
                    $modesToProcess = is_array($data['type_of_modes']) ? $data['type_of_modes'] : [$data['type_of_modes']];
                    Log::info("Using type_of_modes from request", ['modes' => $modesToProcess]);
                } else {
                    // Reload modes relationship to get current modes
                    $courseObj->load('modes');
                    $modesToProcess = $courseObj->modes->pluck('id')->toArray();
                    Log::info("Using existing modes from course", ['modes' => $modesToProcess]);
                }
                
                // Get mode IDs once
                $onlineModeId = Mode::where('name', config('constants.modes.online'))->value('id');
                $inPersonModeId = Mode::where('name', config('constants.modes.in_person'))->value('id');
                Log::info("Mode IDs", [
                    'online_mode_id' => $onlineModeId,
                    'in_person_mode_id' => $inPersonModeId
                ]);
                
                $pricesCreated = 0;
                BillingPeriod::all()->each(function ($billingPeriod) use ($courseObj, $data, $modesToProcess, $onlineModeId, $inPersonModeId, &$pricesCreated) {
                    $billingId = $billingPeriod->id;
                    Log::info("Processing billing period {$billingId}");

                    // Get price amounts from validated data
                    $onlineAmount = $data["amount_for_online_{$billingId}"] ?? null;
                    $inPersonAmount = $data["amount_for_in_person_{$billingId}"] ?? null;
                    
                    if (in_array($onlineModeId, $modesToProcess) && !empty($onlineAmount)) {
                        $coursePriceData = [
                            'course_id' => $courseObj->id,
                            'billing_period_id' => $billingId,
                            'amount' => $onlineAmount,
                            'mode_id' => $onlineModeId,
                        ];
                        $price = $courseObj->prices()->create($coursePriceData);
                        $pricesCreated++;
                        Log::info("Created online price", [
                            'price_id' => $price->id,
                            'amount' => $price->amount,
                            'billing_period_id' => $billingId
                        ]);
                    }

                    if (in_array($inPersonModeId, $modesToProcess) && !empty($inPersonAmount)) {
                        $coursePriceData = [
                            'course_id' => $courseObj->id,
                            'billing_period_id' => $billingId,
                            'amount' => $inPersonAmount,
                            'mode_id' => $inPersonModeId,
                        ];
                        $price = $courseObj->prices()->create($coursePriceData);
                        $pricesCreated++;
                        Log::info("Created in-person price", [
                            'price_id' => $price->id,
                            'amount' => $price->amount,
                            'billing_period_id' => $billingId
                        ]);
                    }
                });
                
                Log::info("Course prices updated for course ID: {$courseObj->id}", [
                    'price_fields_updated' => $priceFieldsToCheck,
                    'modes' => $modesToProcess,
                    'prices_created' => $pricesCreated,
                    'final_prices_count' => $courseObj->prices()->count()
                ]);
            } else {
                Log::info("No price updates needed");
            }

            // Update mode features
            if (isset($data['online_features_names']) && !empty($data['online_features_names'])) {
                $onlineFeatures = is_array($data['online_features_names']) ? $data['online_features_names'] : [$data['online_features_names']];
                $onlineFeatures = array_filter($onlineFeatures);
                
                // Delete existing online mode features
                $courseObj->modefeatures()->whereNotNull('online_features_names')->delete();
                
                // Create new online features
                foreach ($onlineFeatures as $name) {
                    if (!empty(trim($name))) {
                        $courseObj->modefeatures()->create([
                            'course_id' => $courseObj->id,
                            'online_features_names' => trim($name)
                        ]);
                    }
                }
                Log::info("Online features updated", ['count' => count($onlineFeatures)]);
            }

            if (isset($data['in_person_features_names']) && !empty($data['in_person_features_names'])) {
                $inPersonFeatures = is_array($data['in_person_features_names']) ? $data['in_person_features_names'] : [$data['in_person_features_names']];
                $inPersonFeatures = array_filter($inPersonFeatures);
                
                // Delete existing in-person mode features
                $courseObj->modefeatures()->whereNotNull('in_person_features_names')->delete();
                
                // Create new in-person features
                foreach ($inPersonFeatures as $name) {
                    if (!empty(trim($name))) {
                        $courseObj->modefeatures()->create([
                            'course_id' => $courseObj->id,
                            'in_person_features_names' => trim($name)
                        ]);
                    }
                }
                Log::info("In-person features updated", ['count' => count($inPersonFeatures)]);
            }

            // Handle image upload/replacement
            Log::info("Checking for course_image file", [
                'has_file' => $request->hasFile('course_image'),
                'files_all' => array_keys($request->allFiles()),
                'files_collection' => $request->files->all()
            ]);
            
            if ($request->hasFile('course_image')) {
                try {
                    // Clear old media collection to remove old image from Cloudflare R2
                    $courseObj->clearMediaCollection('course_image');
                    
                    // Use queue job for fast response and background R2 upload
                    $file = $request->file('course_image');
                    
                    // Store file temporarily in local storage for queue processing
                    $tempPath = $file->store('temp/course_images', 'local');
                    
                    if (!$tempPath) {
                        throw new Exception('Failed to store temporary image file.');
                    }
                    
                    // Dispatch job for background processing
                    UploadCourseImageJob::dispatch(
                        $courseObj->id,
                        $tempPath,
                        $file->getClientOriginalName(),
                        $file->getMimeType()
                    );
                    
                    Log::info("Course image upload job dispatched for course ID: {$courseObj->id}", [
                        'temp_path' => $tempPath,
                        'original_name' => $file->getClientOriginalName()
                    ]);
                } catch (Exception $e) {
                    Log::error("Failed to process course image upload. Message => {$e->getMessage()}, File => {$e->getFile()}, Line => {$e->getLine()}.");
                    // Don't fail the entire update if image upload fails
                }
            }

            // Update relationships if provided
            Log::info("Checking relationship updates");
            
            // Handle subject_ids array
            if (isset($data['subject_ids']) && !empty($data['subject_ids'])) {
                $subjectIds = is_array($data['subject_ids']) ? $data['subject_ids'] : [$data['subject_ids']];
                $subjectIds = array_filter($subjectIds);
                
                Log::info("Subject IDs received", [
                    'subject_ids' => $subjectIds,
                    'count' => count($subjectIds)
                ]);
                
                if (!empty($subjectIds)) {
                    $subjectData = collect($subjectIds)->mapWithKeys(fn($id) => [
                        $id => ['created_at' => now(), 'updated_at' => now()]
                    ])->toArray();
                    $courseObj->subjects()->sync($subjectData);
                    Log::info("Subjects synced successfully", ['synced_count' => count($subjectIds)]);
                } else {
                    $courseObj->subjects()->sync([]);
                }
            }

            // Handle location_ids array
            if (isset($data['location_ids']) && !empty($data['location_ids'])) {
                $locationIds = is_array($data['location_ids']) ? $data['location_ids'] : [$data['location_ids']];
                $locationIds = array_filter($locationIds);
                
                Log::info("Location IDs received", [
                    'location_ids' => $locationIds,
                    'count' => count($locationIds)
                ]);
                
                if (!empty($locationIds)) {
                    $locationData = collect($locationIds)->mapWithKeys(fn($id) => [
                        $id => ['created_at' => now(), 'updated_at' => now()]
                    ])->toArray();
                    $courseObj->locations()->sync($locationData);
                    Log::info("Locations synced successfully", ['synced_count' => count($locationIds)]);
                } else {
                    $courseObj->locations()->sync([]);
                }
            }

            // Handle type_of_modes array
            if (isset($data['type_of_modes']) && !empty($data['type_of_modes'])) {
                $modeIds = is_array($data['type_of_modes']) ? $data['type_of_modes'] : [$data['type_of_modes']];
                $modeIds = array_filter($modeIds);
                
                Log::info("Type of modes received", [
                    'mode_ids' => $modeIds,
                    'count' => count($modeIds)
                ]);
                
                if (!empty($modeIds)) {
                    $modeData = collect($modeIds)->mapWithKeys(fn($id) => [
                        $id => ['created_at' => now(), 'updated_at' => now()]
                    ])->toArray();
                    $courseObj->modes()->sync($modeData);
                    Log::info("Modes synced successfully", ['synced_count' => count($modeIds)]);
                } else {
                    $courseObj->modes()->sync([]);
                }
            }

            // Handle features_names array
            if (isset($data['features_names']) && !empty($data['features_names'])) {
                $featuresNames = is_array($data['features_names']) ? $data['features_names'] : [$data['features_names']];
                $featuresNames = array_filter($featuresNames);
                
                Log::info("Features names received", [
                    'features_names' => $featuresNames,
                    'count' => count($featuresNames)
                ]);
                
                // Delete existing features
                $courseObj->features()->delete();
                
                // Create new features
                foreach ($featuresNames as $name) {
                    if (!empty(trim($name))) {
                        $courseObj->features()->create([
                            'created_id' => Auth::id(),
                            'course_id' => $courseObj->id,
                            'name' => trim($name)
                        ]);
                    }
                }
                Log::info("Features updated successfully", ['count' => count($featuresNames)]);
            }

            // Handle acdemic_year_id
            if (isset($data['acdemic_year_id']) && !empty($data['acdemic_year_id'])) {
                $academicYearId = $data['acdemic_year_id'];
                Log::info("Academic year ID received", ['academic_year_id' => $academicYearId]);
                
                $courseObj->acdemicyears()->sync([$academicYearId => [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]]);
                Log::info("Academic year synced successfully");
            }

            Log::info("All updates completed, committing transaction for course ID: {$id}");
            DB::commit();
            Log::info("Transaction committed successfully for course ID: {$id}");

            // Reload course to get fresh data
            $courseObj->refresh();
            Log::info("Course refreshed after update", [
                'course_id' => $courseObj->id,
                'name' => $courseObj->name,
                'subjects_count' => $courseObj->subjects()->count(),
                'locations_count' => $courseObj->locations()->count(),
                'modes_count' => $courseObj->modes()->count(),
                'prices_count' => $courseObj->prices()->count(),
                'features_count' => $courseObj->features()->count(),
                'academic_years_count' => $courseObj->acdemicyears()->count()
            ]);

            // Dispatch Stripe update job after successful commit
            // Always dispatch to ensure Stripe is in sync (handles name, description, image, and price changes)
            try {
                Log::info("Dispatching Stripe update job for course ID: {$courseObj->id}");
                dispatch(new UpdateStripePrice($courseObj->id));
                Log::info("Stripe update job dispatched successfully for course ID: {$courseObj->id}");
            } catch (Exception $e) {
                Log::warning("Failed to dispatch Stripe update job for course ID: {$courseObj->id}. Message => {$e->getMessage()}");
                // Don't fail the update if Stripe job dispatch fails
            }
            
            Log::info("=== COURSE UPDATE COMPLETED SUCCESSFULLY ===", ['course_id' => $id]);
            return sendResponse('Course updated successfully.', 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            Log::error("=== COURSE UPDATE FAILED: Course Not Found ===", [
                'course_id' => $id,
                'user_id' => Auth::id(),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return sendError('error', ['error' => 'Course not found.'], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            Log::error("=== COURSE UPDATE FAILED: Validation Error ===", [
                'course_id' => $id,
                'user_id' => Auth::id(),
                'validation_errors' => $e->errors(),
                'request_data' => $request->all()
            ]);
            return sendError('validation_error', $e->errors(), 422);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("=== COURSE UPDATE FAILED: Exception ===", [
                'course_id' => $id,
                'user_id' => Auth::id(),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'code' => $e->getCode(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);
            return sendError('error', ['error' => 'An error occurred during update. Please try again or contact support if the issue persists.'], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  DeleteCourseRequest  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(DeleteCourseRequest $request, $id)
    {
        try {
            DB::beginTransaction();

            $courseObj = Course::where('created_id', Auth::id())->findOrFail($id);

            // Delete Cloudflare R2 images
            $courseObj->clearMediaCollection('course_image');

            // Delete the course record (cascade will handle related records)
            $courseObj->delete();

            DB::commit();

            // Dispatch Stripe product deletion job asynchronously
            // This won't block the response even if Stripe deletion fails
            dispatch(new DeleteStripeProduct($id));

            return sendResponse('Course deleted successfully.', 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return sendError('error', ['error' => 'Course not found.'], 404);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Failed to delete course. Message => {$e->getMessage()}, File => {$e->getFile()}, Line => {$e->getLine()}, Code => {$e->getCode()}.");
            return sendError('error', ['error' => 'An error occurred during deletion.'], 500);
        }
    }

    /**
     * Toggle course status (activate/inactivate).
     *
     * @param  ToggleCourseStatusRequest  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function toggleStatus(ToggleCourseStatusRequest $request, $id)
    {
        try {
            $courseObj = Course::where('created_id', Auth::id())->findOrFail($id);
            $action = $request->input('action');
            $currentStatus = $courseObj->status;
            
            $activeStatus = config('constants.statuses.APPROVED'); // 2
            $inactiveStatus = config('constants.statuses.REJECTED'); // 3
            
            $statusUpdated = false;
            $newStatus = $currentStatus;
            $statusLabel = '';

            if ($action === 'activate') {
                if ($currentStatus != $activeStatus) {
                    $courseObj->status = $activeStatus;
                    $courseObj->save();
                    $statusUpdated = true;
                    $newStatus = $activeStatus;
                    $statusLabel = 'active';
                } else {
                    $statusLabel = 'active';
                }
            } elseif ($action === 'deactivate') {
                if ($currentStatus != $inactiveStatus) {
                    $courseObj->status = $inactiveStatus;
                    $courseObj->save();
                    $statusUpdated = true;
                    $newStatus = $inactiveStatus;
                    $statusLabel = 'inactive';
                } else {
                    $statusLabel = 'inactive';
                }
            }

            $message = $statusUpdated 
                ? "Course status updated to {$statusLabel} successfully."
                : "Course is already {$statusLabel}.";

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'course_id' => $courseObj->id,
                    'previous_status' => $currentStatus,
                    'current_status' => $newStatus,
                    'status_label' => $statusLabel,
                    'was_updated' => $statusUpdated,
                ],
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return sendError('error', ['error' => 'Course not found.'], 404);
        } catch (Exception $e) {
            Log::error("Failed to toggle course status. Message => {$e->getMessage()}, File => {$e->getFile()}, Line => {$e->getLine()}, Code => {$e->getCode()}.");
            return sendError('error', ['error' => 'An error occurred while toggling course status.'], 500);
        }
    }
}
