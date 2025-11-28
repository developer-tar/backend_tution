<?php

namespace App\Http\Requests\Api\Admin;

use App\Models\MockExam;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;

class ToggleMockExamStatusRequest extends FormRequest
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
            'mock_exam.exists' => 'The mock exam does not exist.',
        ];
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $mockExamId = $this->route('mockExam');
            
            // Check if mock exam exists
            $mockExam = MockExam::find($mockExamId);
            
            if (!$mockExam) {
                $validator->errors()->add('mock_exam', 'The mock exam does not exist.');
            }
        });
    }
}

