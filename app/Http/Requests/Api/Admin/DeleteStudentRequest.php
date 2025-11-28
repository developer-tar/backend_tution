<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use App\Models\StudentDetail;
use App\Models\ManageStudentRecord;

class DeleteStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $studentId = $this->route('student');
            
            // Find student by StudentDetail ID or User ID
            $studentDetail = StudentDetail::find($studentId);
            if (!$studentDetail) {
                $studentDetail = StudentDetail::where('child_id', $studentId)->first();
            }
            
            if (!$studentDetail) {
                $validator->errors()->add('student', 'The student does not exist.');
                return;
            }

            // Check if student has assigned courses (for information, not blocking)
            $studentUserId = $studentDetail->child_id;
            $hasAssignedCourses = ManageStudentRecord::where('buyer_id', $studentUserId)->exists();
            
            // Store in request for controller use
            if ($hasAssignedCourses) {
                $this->merge(['has_assigned_courses' => true, 'assigned_courses_count' => ManageStudentRecord::where('buyer_id', $studentUserId)->count()]);
            }
        });
    }
}



