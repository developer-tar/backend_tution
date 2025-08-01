<?php

namespace App\Http\Requests\Api\Admin;

use App\Models\BillingPeriod;
use Illuminate\Foundation\Http\FormRequest;

class StoreCourseRequest extends FormRequest {
    public function authorize(): bool {
        return true;
    }

    public function rules(): array {
        $billingCycles = BillingPeriod::pluck('id')->toArray();
        return array_merge(
            [
                'name' => ['required', 'string', 'max:100', 'unique:courses,name'],
                
                'subject_ids' => ['required', 'array'],
                'subject_ids.*' => ['integer', 'exists:subjects,id'],

                'type_of_modes' => ['required', 'array'],
                'type_of_modes.*' => ['integer', 'exists:modes,id'],

                'location_ids' => ['required', 'array'],
                'location_ids.*' => ['integer', 'exists:locations,id'],

                'features_names' => ['required', 'array'],
                'features_names.*' => ['string', 'min:50', 'max:500'],

                'acdemic_year_id' => ['required', 'integer', 'exists:acdemic_years,id'],
                'course_image' => ['required', 'image', 'max:10240'],
                'description' => ['required', 'string', 'min:500', 'max:10000'],
            ],

            collect($billingCycles)->mapWithKeys(function ($cycle) {
                return [
                    "amount_$cycle" => ['required', 'numeric', 'regex:/^\d{1,6}(\.\d{1,2})?$/']
                ];
            })->toArray()
        );
    }

    public function messages(): array {
        $billingPeriods = BillingPeriod::pluck('name', 'id')->toArray();
  
        $dynamicAmountMessages = [];

        foreach ($billingPeriods as $id => $label) {
            $field = "amount_{$id}";

            $dynamicAmountMessages["{$field}.required"] = "The price for {$label} is required.";
            $dynamicAmountMessages["{$field}.numeric"] = "The price for {$label} must be a valid number.";
            $dynamicAmountMessages["{$field}.regex"] = "The price for {$label} must be a number with up to 2 decimal places (e.g., 9999.99).";
        }

        return array_merge([
            'name.required' => 'Course name is required.',
            'name.unique' => 'A course with this name already exists.',


            'subject_ids.required' => 'At least one subject is required.',
            'subject_ids.*.exists' => 'One or more selected subjects are invalid.',

            'type_of_modes.required' => 'At least one mode of learning must be selected.',
            'type_of_modes.*.exists' => 'One or more selected modes are invalid.',

            'location_ids.required' => 'Please select at least one location.',
            'location_ids.*.exists' => 'One or more selected locations are invalid.',

            'features_names.required' => 'Please provide at least one feature.',
            'features_names.*.string' => 'Each feature must be a valid text.',
            'features_names.*.min' => 'Each feature must be at least 50 characters long.',
            'features_names.*.max' => 'Each feature must not exceed 500 characters.',

            'acdemic_year_id.required' => 'Academic year is required.',
            'acdemic_year_id.exists' => 'Selected academic year is invalid.',

            'course_image.required' => 'Course image is required.',
            'course_image.image' => 'Uploaded file must be an image.',
            'course_image.max' => 'Course image size must not exceed 10MB.',

            'description.required' => 'Course description is required.',
            'description.min' => 'Description must be at least 500 characters long.',
            'description.max' => 'Description must not exceed 10000 characters.',
        ], $dynamicAmountMessages);
    }
}
