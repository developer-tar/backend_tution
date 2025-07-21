<?php

namespace App\Http\Controllers\Api\Admin\Assign;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\TimeSlotRequest;
use App\Models\CourseTimeSlot;
use Illuminate\Support\Facades\Log;
use Exception;

class TimeSlotController extends Controller
{
    public function saveAndNext(TimeSlotRequest $request)
    {
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
    public function getLocation(){
        return 
    }
    /**
     * Check for overlapping time slots
     */
    private function _isOverlapping($courseId, $locationId, $weekdayId, $newStart, $newEnd)
    {
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
