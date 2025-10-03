<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMockExamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'              => ['required', 'string', 'max:255'],
            'description'       => ['nullable', 'string'],
            'category_id'       => ['required', 'integer', 'exists:mock_exam_categories,id'],
            'format_id'            => ['required', 'string', 'exists:formats,id'],
            'price'             => ['required', 'numeric', 'min:0'],
            'duration_minutes'  => ['nullable', 'integer', 'min:1'],
            'school_id'         => ['nullable', 'integer', 'exists:schools,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Mock exam name is required.',
            'category_id.required' => 'Category is required.',
            'category_id.exists' => 'Selected category does not exist.',
            'format_id.required' => 'Format is required.',
            'format_id.exists' => 'Selected format does not exist.',
            'price.required' => 'Price is required.',
            'price.min' => 'Price must be at least 0.',
            'school_id.exists' => 'Selected school does not exist.',
        ];
    }
}
