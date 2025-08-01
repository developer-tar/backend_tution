<?php

namespace App\Http\Controllers\Api\Admin\Assign;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\TimeSlotRequest;
use App\Http\Requests\Api\Admin\UpdateTimeSlotRequest;
use App\Models\AcdemicCourse;
use App\Models\CourseTimeSlot;
use App\Models\Location;
use Illuminate\Support\Facades\Log;
use Exception;
use Illuminate\Http\Request;
use PDO;

class TimeSlotController extends Controller {

    public function getLocation(AcdemicCourse $ca) {
        try {
            $data = Location::getByCourseId($ca->course_id);

            return $data->isNotEmpty()
                ? sendResponse($data, "Location fetch successfully!!")
                : sendError('Not Found');
        } catch (Exception $e) {
            return errorLog("Failed to fetch the location: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }


    public function index(Request $request) {
        try {
            $data = CourseTimeSlot::fetchFiltered($request);

            if ($data->isNotEmpty()) {
                return sendResponse($data, "Timeslot fetch successfully!!");
            }

            return sendError('Not Found');
        } catch (Exception $e) {
            return errorLog("Failed to fetch timeslots: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }


    public function save(TimeSlotRequest $request) {
        try {
            $result = CourseTimeSlot::storeTimeSlot($request->validated());

            return $result['success']
                ? sendResponse(['timeslot_id' => $result['timeslot']->id], $result['message'], 201)
                : sendError('Error', ['error' => $result['message']], 400);
        } catch (Exception $e) {
            return errorLog("Failed to save timeslot: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }


    public function show(CourseTimeSlot $timeslot) {
        try {
            $data = $timeslot->except('deleted_at', 'created_at', 'updated_at');

            return sendResponse($data, "Timeslot fetch successfully!!");
        } catch (Exception $e) {
            return errorLog("Failed to fetch the location: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }
    public function update(UpdateTimeSlotRequest $request) {
        try {
            $result = CourseTimeSlot::updateTimeSlot($request->validated());

            return $result['success']
                ? sendResponse(['timeslot_id' => $result['timeslot']->id], $result['message'], 201)
                : sendError('Error', ['error' => $result['message']], 400);
        } catch (Exception $e) {
            return errorLog("Failed to update timeslot: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }
    public function getTimeSlot($aCId, $locId, $wId) {
        try {
            $data = CourseTimeSlot::getTimeSlots($aCId, $locId, $wId);

            return $data->isNotEmpty()
                ? sendResponse($data, "Timeslot fetch successfully!!")
                : sendError('Not Found');
        } catch (Exception $e) {
            return errorLog("Failed to fetch timeslot: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    public function destroy(CourseTimeSlot $timeslot) {
        try {
            return $timeslot->deleteSlot()
                ? sendResponse("delete", "Timeslot has been deleted successfully!!")
                : sendError('Not Found');
        } catch (Exception $e) {
            return errorLog("Failed to delete timeslot: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }
}
