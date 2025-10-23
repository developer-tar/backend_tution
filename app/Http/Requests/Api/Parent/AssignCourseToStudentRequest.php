<?php

namespace App\Http\Requests\Api\Parent;

use App\Models\CoursePrice;
use App\Models\ManageStudentRecord;
use App\Models\StudentDetail;
use App\Services\ParentCourseService;
use Illuminate\Foundation\Http\FormRequest;

class AssignCourseToStudentRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['required', 'integer', 'exists:users,id'],
            'course_id' => ['required', 'integer', 'exists:courses,id'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $parentId = auth()->user()->id;
            $courseId = $this->input('course_id');
            $parentCourseService = app(ParentCourseService::class);

            // Check if parent has subscription for this course
            if (!$parentCourseService->parentHasSubscriptionForCourse($parentId, $courseId)) {
                $validator->errors()->add('course_id', 'You do not have subscription for this course.');
                return;
            }

            // Get student IDs (always array now)
            $studentIds = $this->input('student_ids');

            // Validate each student
            foreach ($studentIds as $studentId) {
                // Check if student belongs to parent
                if (!$parentCourseService->verifyStudentBelongsToParent($parentId, $studentId)) {
                    $validator->errors()->add('student_id', "Student with ID {$studentId} does not belong to you.");
                    return;
                }

                // Check if course is already assigned to student
                $existingAssignment = ManageStudentRecord::where('buyer_id', $studentId)
                    ->where('course_id', $courseId)
                    ->exists();

                if ($existingAssignment) {
                    $validator->errors()->add('course_id', "Course is already assigned to student with ID {$studentId}.");
                    return;
                }
            }
        });
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'student_ids.required' => 'Student IDs are required.',
            'student_ids.array' => 'Student IDs must be an array.',
            'student_ids.min' => 'At least one student must be selected.',
            'student_ids.*.required' => 'Each student ID is required.',
            'student_ids.*.integer' => 'Each student ID must be a valid number.',
            'student_ids.*.exists' => 'One or more selected students do not exist.',
            'course_id.required' => 'Course ID is required.',
            'course_id.integer' => 'Course ID must be a valid number.',
            'course_id.exists' => 'Selected course does not exist.',
        ];
    }
}
