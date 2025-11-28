<?php

namespace App\Http\Requests\Api\Admin;

use App\Models\CourseAssignment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;

class ToggleAssignmentStatusRequest extends FormRequest
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
            'assignment.exists' => 'The assignment does not exist.',
        ];
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $assignmentId = $this->route('assignment');
            
            // Check if assignment exists
            $assignment = CourseAssignment::find($assignmentId);
            
            if (!$assignment) {
                $validator->errors()->add('assignment', 'The assignment does not exist.');
            }
        });
    }
}

