<?php

namespace App\Http\Controllers\Api\Admin\Assign;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\TimeSlotRequest;
use App\Models\AcdemicCourse;
use App\Models\CourseTimeSlot;
use App\Models\Location;
use Illuminate\Support\Facades\Log;
use Exception;
use Illuminate\Http\Request;

class TimeSlotController extends Controller {

    public function index(Request $request) {
        try {
            $acId = $request->filled('academic_course_id') ? $request->input('academic_course_id') : null;
            $locId = $request->filled('location_id') ? $request->input('location_id') : null;
            $weekDayId = $request->filled('weekday_id') ? $request->input('weekday_id') : null;

            $data = CourseTimeSlot::with('locations:id,name', 'courses:id,name')
                ->select('id', 'start_time', 'end_time', 'location_id', 'course_id')
                ->orWhere('academic_course_id', $acId)
                ->orWhere('location_id', $locId)
                ->orWhere('weekday_id', $weekDayId)
                ->orderBy('end_time')
                ->get();
            // dd($data);
            if ($data->isNotEmpty()) {
                //   $cartItems = $cartItems->transform(function ($item) {
                //     $course = $item->course;
                //     return [
                //         'cart_id' => $item->id,
                //         'course_name' => $course->name ?? 'Unknown Product',
                //         'course_image' =>  $course->getFirstMediaUrl('course_image') ?? null,
                //         'quantity' => $item->quantity ?? 1,
                //         'course_price' => $course->amount ?? 0,
                //         'total_price' => ($item->quantity ?? 1) * ($course->amount ?? 0),
                //     ];
                // });

                // $data = $data->transform(function ($item){
                //     re
                // })
                return sendResponse($data, "Time slot fetch successfully!!");
            }
            return sendError('Not Found');
        } catch (Exception $e) {
            errorLog("Failed to fetch the location: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
            return response()->json(['error' => 'An error occurred while saving the timeslot.'], 500);
        }
    }
    public function getLocation(AcdemicCourse $ca) {
        try {
            $courseId = $ca->course_id;
            $data = Location::with(['courses' => function ($q) use ($courseId) {
                $q->where('courses.id', $courseId)
                    ->select('courses.id'); // ✅ disambiguate
            }])
                ->whereHas('courses', function ($q) use ($courseId) {
                    $q->where('courses.id', $courseId);
                })
                ->select('locations.id', 'locations.name') // ✅ also disambiguate here
                ->get();

            if ($data->isNotEmpty()) {
                $data = $data->transform(function ($item) {
                    return [
                        'id' => $item->id,
                        'name' => $item->name,
                    ];
                });
                return sendResponse($data, "Location fetch successfully!!");
            }
            return sendError('Not Found');
        } catch (Exception $e) {
            errorLog("Failed to fetch the location: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
            return response()->json(['error' => 'An error occurred while saving the timeslot.'], 500);
        }
    }

    public function saveAndNext(TimeSlotRequest $request) {
        try {
            $data = $request->all();

            // Check for overlapping time slot
            if ($this->_isOverlapping(
                $data['course_id'],
                $data['location_id'],
                $data['weekday_id'],
                $data['start_time'],
                $data['end_time']
            )) {
                return response()->json(['error' => 'This time slot overlaps with another.'], 422);
            }

            // Save the timeslot
            $timeSlot = CourseTimeSlot::firstOrCreate($data);

            if ($timeSlot->wasRecentlyCreated) {
                return response()->json([
                    'success' => true,
                    'message' => 'New Timeslot created successfully.',
                    'data' => ['timeslot_id' => $timeSlot->id],
                ]);
            }

            return response()->json(['error' => 'This timeslot already exists.'], 400);
        } catch (Exception $e) {

            Log::error("Failed to save timeslot: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
            return response()->json(['error' => 'An error occurred while saving the timeslot.'], 500);
        }
    }

    public function getTimeSlot($aCId, $locId, $wId) {
        try {
            $data = CourseTimeSlot::select('id', 'start_time', 'end_time')
                ->where(['academic_course_id' => $aCId, 'location_id' => $locId, 'weekday_id' => $wId])
                ->orderBy('end_time')
                ->get();
            // dd($data);
            if ($data->isNotEmpty()) {
                return sendResponse($data, "Time slot fetch successfully!!");
            }
            return sendError('Not Found');
        } catch (Exception $e) {
            errorLog("Failed to fetch the location: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
            return response()->json(['error' => 'An error occurred while saving the timeslot.'], 500);
        }
    }
    /**
     * Check for overlapping time slots
     */
    private function _isOverlapping($courseId, $locationId, $weekdayId, $newStart, $newEnd) {
        return CourseTimeSlot::where('course_id', $courseId)
            ->where('location_id', $locationId)
            ->where('weekday_id', $weekdayId)
            ->where(function ($q) use ($newStart, $newEnd) {
                $q->where(function ($query) use ($newStart, $newEnd) {
                    $query->where('start_time', '<', $newEnd)
                        ->where('end_time', '>', $newStart);
                });
            })
            ->exists();
    }
}
