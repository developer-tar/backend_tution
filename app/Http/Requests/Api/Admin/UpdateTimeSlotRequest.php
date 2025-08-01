<?php

namespace App\Http\Requests\Api\Admin;

use App\Models\AcdemicCourse;
use App\Models\CourseTimeSlot;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateTimeSlotRequest extends FormRequest {

    /**
     * Determine if the user is authorized to make this request.
     */
    protected $validLocationIds;
    public function authorize(): bool {
        return true; // Authorize the request
    }

    /** 
     * Define the validation rules.
     */
    public function rules(): array {
     
        return [
            'academic_course_id' => 'required|exists:acdemic_course,id',
            'weekday_id' => 'required|exists:week_days,id',
            'location_id' => 'required|exists:locations,id',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'seats' => 'required|integer|min:1',
            'course_id' => 'nullable|exists:courses,id',
            'class_name' => 'required|string|max:255',
            'timeslot_id' => 'required|exists:course_time_slots,id',
        ];
    }

    /**
     * Custom error messages.
     */
    public function messages(): array {
        return [
            'academic_course_id.required' => 'Course academic is required.',
            'academic_course_id.exists' => 'The selected course academic is invalid.',
            'weekday_id.required' => 'Week day is required.',
            'weekday_id.exists' => 'The selected week day is invalid.',
            'location_id.required' => 'Location is required.',
            'start_time.required' => 'Start time is required.',
            'start_time.date_format' => 'Start time must be in HH:MM format (e.g., 10:00).',
            'end_time.required' => 'End time is required.',
            'end_time.date_format' => 'End time must be in HH:MM format (e.g., 12:00).',
            'end_time.after' => 'End time must be after start time.',
            'seats.required' => 'Seats are required.',
            'seats.integer' => 'Seats must be a whole number.',
            'seats.min' => 'Seats must be at least 1.',
            'course_name.required' => 'Class name is required.',
            'course_name.string' => 'Class name must be a string.',
            'course_name.max' => 'Class name must not exceed 255 characters.',
            'timeslot_id.required' => 'Timeslot ID is required.',
            'timeslot_id.exists' => 'Timeslot not found.',
        ];
    }

    /**
     * Add additional validation logic.
     */
    public function withValidator(Validator $validator): void {
        $validator->after(function ($validator) {
            
            if (!$validator->failed()) {
                $this->validateLocationBelongsToCourse($validator);
                $this->validateMinimumDuration($validator);
                $this->validateOverlappingTimeslot($validator);
            } //when not any validation is pending 
        });
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation() {
        $academicCourseId = $this->input('academic_course_id');
        $locationId = $this->input('location_id');
        if ($academicCourseId && $locationId) {
            $ac = AcdemicCourse::with('courses:id', 'courses.locations:id')->select('course_id')->find($academicCourseId);
            if ($ac) {
                $this->validLocationIds = $ac?->courses->locations->pluck('id')->toArray();

                $this->merge(['course_id' => $ac->course_id]);
            }
        }
    }
    private function validateLocationBelongsToCourse($validator): void {
       
        if (!in_array($this->input('location_id'), $this->validLocationIds)) {
            $validator->errors()->add('location_id', 'The selected location is not valid for the selected course.');
        }
    }


    private function validateMinimumDuration($validator): void {
        $start = $this->input('start_time');
        $end = $this->input('end_time');
        
        if ($start && $end) {
            $startTime = Carbon::createFromFormat('H:i', $start);
            $endTime = Carbon::createFromFormat('H:i', $end);
            $diffMinutes = $startTime->diffInMinutes($endTime);

            if ($diffMinutes <= config('constants.gap_between_start_end_time')) {
                $validator->errors()->add('end_time', 'Start and end time must be at least 30 minutes apart.');
            }
        }
    }

    private function validateOverlappingTimeslot($validator): void {
        if (!$this->filled(['course_id', 'location_id', 'weekday_id', 'start_time', 'end_time'])) {
            return;
        }

        $courseId = $this->input('course_id');
        $locationId = $this->input('location_id');
        $weekdayId = $this->input('weekday_id');
        $startTime = $this->input('start_time');
        $endTime = $this->input('end_time');
        $timeslotId = $this->input('timeslot_id');

        if ($this->_isOverlapping($courseId, $locationId, $weekdayId, $startTime, $endTime, $timeslotId)) {
            $validator->errors()->add('end_time', 'This timeslot overlaps with another.');
        }
    }

    private function _isOverlapping($courseId, $locationId, $weekdayId, $newStart, $newEnd, $timeslotId) {
        return CourseTimeSlot::where('course_id', $courseId)
            ->where('location_id', $locationId)
            ->where('weekday_id', $weekdayId)
            ->where(function ($q) use ($newStart, $newEnd) {
                $q->where(function ($query) use ($newStart, $newEnd) {
                    $query->where('start_time', '<', $newEnd)
                        ->where('end_time', '>', $newStart);
                });
            })
            ->whereNot('id', $timeslotId)
            ->exists();
    }
}
