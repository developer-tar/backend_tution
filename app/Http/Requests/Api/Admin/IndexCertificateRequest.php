<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexCertificateRequest extends FormRequest
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
            'award_id' => ['nullable', 'integer', Rule::exists('awards', 'id')],
            'student_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'status' => 'nullable|integer|in:0,1',
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
            'award_id.integer' => 'The award ID must be an integer.',
            'award_id.exists' => 'The selected award does not exist.',
            'student_id.integer' => 'The student ID must be an integer.',
            'student_id.exists' => 'The selected student does not exist.',
            'status.integer' => 'The status must be an integer.',
            'status.in' => 'The status must be either 0 or 1.',
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
            'award_id' => 'award',
            'student_id' => 'student',
            'status' => 'status',
            'per_page' => 'per page',
        ];
    }
}

