<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;

class IndexAwardRequest extends FormRequest
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
            'search' => 'nullable|string|max:255',
            'status' => 'nullable|integer|in:0,1',
            'type' => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|min:1|max:100',
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
            'search.string' => 'The search must be a string.',
            'search.max' => 'The search may not be greater than :max characters.',
            'status.integer' => 'The status must be an integer.',
            'status.in' => 'The status must be either 0 or 1.',
            'type.string' => 'The type must be a string.',
            'type.max' => 'The type may not be greater than :max characters.',
            'per_page.integer' => 'The per page must be an integer.',
            'per_page.min' => 'The per page must be at least :min.',
            'per_page.max' => 'The per page may not be greater than :max.',
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
            'search' => 'search',
            'status' => 'status',
            'type' => 'type',
            'per_page' => 'per page',
        ];
    }
}
