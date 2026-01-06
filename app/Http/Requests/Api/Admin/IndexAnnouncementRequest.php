<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;

class IndexAnnouncementRequest extends FormRequest
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
            'status' => 'nullable|integer|in:' . config('constants.announcement_status.draft') . ',' . config('constants.announcement_status.publish'),
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
            'status.in' => 'The status must be either draft or publish.',
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
            'per_page' => 'per page',
        ];
    }
}
