<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GetClassesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'course_id' => [
                'required',
                'integer',
                Rule::exists('courses', 'id'),
            ],
            'academic_year_id' => [
                'required',
                'integer',
                Rule::exists('acdemic_years', 'id'),
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'course_id.required' => 'The course ID is required.',
            'course_id.integer' => 'The course ID must be an integer.',
            'course_id.exists' => 'The selected course does not exist.',
            'academic_year_id.required' => 'The academic year ID is required.',
            'academic_year_id.integer' => 'The academic year ID must be an integer.',
            'academic_year_id.exists' => 'The selected academic year does not exist.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array
     */
    public function attributes(): array
    {
        return [
            'course_id' => 'course',
            'academic_year_id' => 'academic year',
        ];
    }
}
