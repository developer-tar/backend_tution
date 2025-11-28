<?php

namespace App\Http\Requests\Api\Admin;

use App\Models\CourseAssignment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;

class UpdateAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $assignmentId = $this->route('assignment');
        
        return [
            'week_ids' => ['sometimes', 'required', 'array'],
            'week_ids.*' => ['integer', 'exists:weeks,id'],
            'acdemic_course_id' => ['sometimes', 'required', 'integer', 'exists:acdemic_course,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'week_ids.required' => 'At least one week must be selected.',
            'week_ids.array' => 'Weeks must be provided as an array.',
            'week_ids.*.integer' => 'Each week ID must be an integer.',
            'week_ids.*.exists' => 'One or more selected weeks do not exist.',
            'acdemic_course_id.required' => 'Academic course is required.',
            'acdemic_course_id.integer' => 'Academic course ID must be an integer.',
            'acdemic_course_id.exists' => 'The selected academic course does not exist.',
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
                return;
            }

            // Get academic course ID from request or existing assignment
            $academicCourseId = $this->input('acdemic_course_id', $assignment->acdemic_course_id);
            $weekIds = $this->input('week_ids', []);

            if (!empty($weekIds) && $academicCourseId) {
                // Check for duplicates excluding current assignment
                $existing = CourseAssignment::where('acdemic_course_id', $academicCourseId)
                    ->whereIn('week_id', $weekIds)
                    ->where('id', '!=', $assignmentId)
                    ->pluck('week_id')
                    ->toArray();

                if (!empty($existing)) {
                    $weekNumbers = \App\Models\Week::whereIn('id', $existing)
                        ->pluck('week_number')
                        ->implode(', ');
                    $validator->errors()->add('week_ids', "Some weeks are already assigned to this academic course: {$weekNumbers}");
                }
            }
        });
    }
}

