<?php

namespace App\Http\Requests\Api\Admin;

use App\Models\BillingPeriod;
use App\Models\Mode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;

class StoreCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $billingCycles = BillingPeriod::pluck('id')->toArray();

        $selectedModeNames = Mode::whereIn('id', $this->input('type_of_modes', []))
            ->pluck('name')
            ->map(fn($mode) => strtolower(trim($mode)))
            ->toArray();

        $billingRules = [];

        $amountValidation = [
            'nullable',
            'numeric',
            'regex:/^\d{1,6}(\.\d{1,2})?$/',
        ];

        foreach ($billingCycles as $cycle) {
            if (in_array('online', $selectedModeNames)) {
                $billingRules["amount_for_online_{$cycle}"] = $amountValidation;

                $billingRules['online_features_names'] = ['required', 'array'];
                $billingRules["online_features_names.*"] = ['string', 'min:50', 'max:500'];
            }

            if (in_array('in person', $selectedModeNames)) {
                $billingRules["amount_for_in_person_{$cycle}"] = $amountValidation;
                $billingRules['in_person_features_names'] = ['required', 'array'];
                $billingRules["in_person_features_names.*"] = ['string', 'min:50', 'max:500'];
            }
        }

        return array_merge([
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

            'course_image' => ['nullable', 'image', 'max:10240'],
            'description' => ['required', 'string', 'min:500', 'max:10000'],
        ], $billingRules);
    }

    public function messages(): array
    {
        $billingPeriods = BillingPeriod::pluck('name', 'id')->toArray();

        $selectedModeNames = Mode::whereIn('id', $this->input('type_of_modes', []))
            ->pluck('name')
            ->map(fn($mode) => strtolower(trim($mode)))
            ->toArray();

        $dynamicAmountMessages = [];

        foreach ($billingPeriods as $id => $label) {
            if (in_array('online', $selectedModeNames)) {
                $field = "amount_for_online_{$id}";
                $dynamicAmountMessages["{$field}.numeric"] = "Online price for {$label} must be a valid number.";
                $dynamicAmountMessages["{$field}.regex"] = "Online price for {$label} must be up to 2 decimal places.";

                $dynamicAmountMessages["online_features_names.required"] = 'Online features are required for courses.';
                $dynamicAmountMessages["online_features_names.*.string"] = 'Each online feature must be valid text.';
                $dynamicAmountMessages["online_features_names.*.min"] = 'Each online feature must be at least 50 characters long.';
                $dynamicAmountMessages["online_features_names.*.max"] = 'Each online feature must not exceed 500 characters.';
            }

            if (in_array('in person', $selectedModeNames)) {
                $field = "amount_for_in_person_{$id}";
                $dynamicAmountMessages["{$field}.numeric"] = "In-person price for {$label} must be a valid number.";
                $dynamicAmountMessages["{$field}.regex"] = "In-person price for {$label} must be up to 2 decimal places.";

                $dynamicAmountMessages["in_person_features_names.required"] = 'In-person features are required for courses.';
                $dynamicAmountMessages["in_person_features_names.*.string"] = 'Each in-person feature must be valid text.';
                $dynamicAmountMessages["in_person_features_names.*.min"] = 'Each in-person feature must be at least 50 characters long.';
                $dynamicAmountMessages["in_person_features_names.*.max"] = 'Each in-person feature must not exceed 500 characters.';
            }
        }

        return array_merge([
            'name.required' => 'Course name is required.',
            'name.unique' => 'A course with this name already exists.',
            'name.max' => 'Course name must not exceed 100 characters.',

            'subject_ids.required' => 'At least one subject must be selected.',
            'subject_ids.*.exists' => 'One or more selected subjects are invalid.',

            'type_of_modes.required' => 'Please select at least one mode.',
            'type_of_modes.*.exists' => 'One or more selected modes are invalid.',

            'location_ids.required' => 'Please select at least one location.',
            'location_ids.*.exists' => 'One or more selected locations are invalid.',

            'features_names.required' => 'Please provide at least one feature.',
            'features_names.*.string' => 'Each feature must be valid text.',
            'features_names.*.min' => 'Each feature must be at least 50 characters long.',
            'features_names.*.max' => 'Each feature must not exceed 500 characters.',

            'acdemic_year_id.required' => 'Academic year is required.',
            'acdemic_year_id.exists' => 'Selected academic year is invalid.',

            'course_image.required' => 'Course image is required.',
            'course_image.image' => 'The uploaded file must be an image.',
            'course_image.max' => 'Course image size must not exceed 10MB.',

            'description.required' => 'Course description is required.',
            'description.min' => 'Description must be at least 500 characters long.',
            'description.max' => 'Description must not exceed 10,000 characters.',


            'amount_for_online_group.required' => 'At least one online price is required.',
            'amount_for_in_person_group.required' => 'At least one in-person price is required.',
        ], $dynamicAmountMessages);
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $billingCycles = BillingPeriod::pluck('id')->toArray();

            $selectedModeNames = Mode::whereIn('id', $this->input('type_of_modes', []))
                ->pluck('name')
                ->map(fn($mode) => strtolower(trim($mode)))
                ->toArray();

            if (in_array('online', $selectedModeNames)) {
                $hasAtLeastOneOnline = collect($billingCycles)->contains(
                    fn($cycle) =>
                    $this->filled("amount_for_online_{$cycle}") &&
                    is_numeric($this->input("amount_for_online_{$cycle}"))
                );

                if (!$hasAtLeastOneOnline) {
                    $validator->errors()->add('amount_for_online_group', 'At least one online amount (for any billing cycle) is required.');
                }
            }

            if (in_array('in person', $selectedModeNames)) {

                $hasAtLeastOneInPerson = collect($billingCycles)->contains(
                    fn($cycle) =>
                    $this->filled("amount_for_in_person_{$cycle}") &&
                    is_numeric($this->input("amount_for_in_person_{$cycle}"))
                );

                if (!$hasAtLeastOneInPerson) {
                    $validator->errors()->add('amount_for_in_person_group', 'At least one in-person amount (for any billing cycle) is required.');
                }
            }
        });
    }
}
