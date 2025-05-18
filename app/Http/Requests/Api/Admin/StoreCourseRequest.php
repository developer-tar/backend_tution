<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Change to authorization logic if needed
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type_of_course' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'min:0'],

            'subject_ids' => ['required', 'array'],
            'subject_ids.*' => ['integer', 'exists:subjects,id'],

            'location_ids' => ['required', 'array'],
            'location_ids.*' => ['integer', 'exists:locations,id'],

            'features_names' => ['required', 'array'],
            'features_names.*' => ['string', 'max:255'],

            'acdemic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'course_image' => ['nullable', 'image', 'max:2048'],
        ];
    }
    public function messages(): array
{
    return [
        'subject_ids.*.exists' => 'One or more selected subjects are invalid.',
        'features_names.*.string' => 'Each feature name must be a valid string.',
    ];
}
}
