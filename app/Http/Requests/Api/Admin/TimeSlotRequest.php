<?php

namespace App\Http\Requests\Api\Admin;

use App\Models\AcdemicCourse;
use App\Models\Course;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class TimeSlotRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorize the request
    }

    /**
     * Define the validation rules.
     */
    public function rules(): array
    {
        return [
            'academic_course_id' => 'required|exists:acdemic_course,id',
            'weekday_id' => 'required|exists:week_days,id',
            'location_id' => 'required',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'seats' => 'required|integer|min:1',
        ];
    }

    /**
     * Custom error messages.
     */
    public function messages(): array
    {
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
        ];
    }

    /**
     * Add additional validation logic.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $academicCourseId = $this->input('academic_course_id');
            $locationId = $this->input('location_id');


            if ($academicCourseId && $locationId) {
                $ac = AcdemicCourse::select('course_id')->find($academicCourseId);

                if ($ac) {
                    $course = Course::with('locations')->find($ac->course_id);

                    $validLocationIds = $course?->locations->pluck('id')->toArray();
                    // Merge course_id into request for later use
                    $this->merge(['course_id' => $ac->course_id]);

                    if (!in_array($locationId, $validLocationIds)) {
                        $validator->errors()->add('location_id', 'The selected location is not valid for the selected course.');
                    }
                    // Enforce 30-minute interval between start_time and end_time
                    $start = $this->input('start_time');
                    $end = $this->input('end_time');

                    if ($start && $end) {
                        $startTime = Carbon::createFromFormat('H:i', $start);
                        $endTime = Carbon::createFromFormat('H:i', $end);
                        $diffMinutes = $startTime->diffInMinutes($endTime);

                        if ($diffMinutes <= 29) {
                            $validator->errors()->add('end_time', 'Start and end time may be equal or  greater than 30 minutes.');
                        }
                    }
                }
            }
        });
    }
}
