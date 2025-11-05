<?php

namespace App\Http\Requests\MasterForm;

use Illuminate\Foundation\Http\FormRequest;

abstract class BaseMasterFormRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Add your authorization logic here
    }

    /**
     * Get the validation rules that apply to the request.
     */
    abstract public function rules(): array;

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'The name field is required.',
            'name.string' => 'The name must be a string.',
            'name.max' => 'The name may not be greater than :max characters.',
            'name.integer' => 'The name must be an integer.',
            'name.min' => 'The name must be at least :min.',
            'start_year.required' => 'The start year field is required.',
            'start_year.integer' => 'The start year must be an integer.',
            'start_year.min' => 'The start year must be at least :min.',
            'start_year.max' => 'The start year may not be greater than :max.',
            'end_year.required' => 'The end year field is required.',
            'end_year.integer' => 'The end year must be an integer.',
            'end_year.min' => 'The end year must be at least :min.',
            'end_year.max' => 'The end year may not be greater than :max.',
            'end_year.gt' => 'The end year must be greater than the start year.',
            'academic_year_id.required' => 'The academic year field is required.',
            'academic_year_id.exists' => 'The selected academic year is invalid.',
            'week_number.required' => 'The week number field is required.',
            'week_number.string' => 'The week number must be a string.',
            'week_number.max' => 'The week number may not be greater than :max characters.',
            'start_date.required' => 'The start date field is required.',
            'start_date.date' => 'The start date must be a valid date.',
            'end_date.required' => 'The end date field is required.',
            'end_date.date' => 'The end date must be a valid date.',
            'end_date.after' => 'The end date must be after the start date.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'academic_year_id' => 'academic year',
            'week_number' => 'week number',
            'start_date' => 'start date',
            'end_date' => 'end date',
            'start_year' => 'start year',
            'end_year' => 'end year',
        ];
    }
}
