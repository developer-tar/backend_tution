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
            'name'              => ['sometimes', 'required', 'string', 'max:255'],
            'description'       => ['sometimes', 'nullable', 'string'],
            'category_id'       => ['sometimes', 'required', 'integer', 'exists:mock_exam_categories,id'],
            'format_id'         => ['sometimes', 'required', 'string', 'exists:formats,id'],
            'price'             => ['sometimes', 'required', 'numeric', 'min:0'],
            'currency'          => ['sometimes', 'nullable', 'string', 'max:10'],
            'duration_minutes'  => ['sometimes', 'nullable', 'integer', 'min:1'],
            'school_id'         => ['sometimes', 'nullable', 'integer', 'exists:schools,id'],
            'mock_exam_image'   => ['sometimes', 'nullable', 'image', 'max:10240'],
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
