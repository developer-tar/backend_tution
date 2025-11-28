<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ToggleCourseStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', 'string', 'in:activate,deactivate'],
        ];
    }

    public function messages(): array
    {
        return [
            'action.required' => 'Action is required.',
            'action.in' => 'Action must be either "activate" or "deactivate".',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Ensure action is lowercase for consistency
        if ($this->has('action')) {
            $this->merge([
                'action' => strtolower($this->input('action')),
            ]);
        }
    }
}

