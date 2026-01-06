<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GetFilteredItemsRequest extends FormRequest
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
            'module_id' => [
                'required',
                'integer',
                Rule::exists('module', 'id'),
            ],
            'academic_year_id' => [
                'nullable',
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
            'module_id.required' => 'The module ID is required.',
            'module_id.integer' => 'The module ID must be an integer.',
            'module_id.exists' => 'The selected module does not exist.',
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
            'module_id' => 'module',
            'academic_year_id' => 'academic year',
        ];
    }
}
