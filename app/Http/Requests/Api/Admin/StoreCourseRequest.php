<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', 'unique:courses,name'],
            'amount' => ['required', 'numeric', 'regex:/^\d{1,6}(\.\d{1,2})?$/'],

            'subject_ids' => ['required', 'array'],
            'subject_ids.*' => ['integer', 'exists:subjects,id'],

            'location_ids' => ['required', 'array'],
            'location_ids.*' => ['integer', 'exists:locations,id'],

            'features_names' => ['required', 'array'],
            'features_names.*' => ['string', 'min:50', 'max:500'],

            'acdemic_year_id' => ['required', 'integer', 'exists:acdemic_years,id'],
            'course_image' => ['required', 'image', 'max:2048'],
            'description' => ['required', 'string', 'min:500', 'max:10000'], // Fixed typo "requrired"
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
