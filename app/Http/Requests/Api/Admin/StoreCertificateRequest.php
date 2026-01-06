<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCertificateRequest extends FormRequest
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
            'award_id' => [
                'required',
                'integer',
                Rule::exists('awards', 'id'),
            ],
            'student_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id'),
            ],
            'issued_date' => 'nullable|date',
            'achievement_details' => 'nullable|string',
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
            'award_id.required' => 'The award ID is required.',
            'award_id.integer' => 'The award ID must be an integer.',
            'award_id.exists' => 'The selected award does not exist.',
            'student_id.required' => 'The student ID is required.',
            'student_id.integer' => 'The student ID must be an integer.',
            'student_id.exists' => 'The selected student does not exist.',
            'issued_date.date' => 'The issued date must be a valid date.',
            'achievement_details.string' => 'The achievement details must be a string.',
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
            'award_id' => 'award',
            'student_id' => 'student',
            'issued_date' => 'issued date',
            'achievement_details' => 'achievement details',
        ];
    }
}

