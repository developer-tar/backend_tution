<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreAssigmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'week_ids' => ['required', 'array'],
            'week_ids.*' => ['integer', 'exists:weeks,id'],
            'acdemic_course_id' => ['required', 'integer', 'exists:acdemic_course,id'],
        ];
    }
}
