<?php

namespace App\Http\Requests\Api\Parent;

use App\Models\StudentDetail;
use Illuminate\Foundation\Http\FormRequest;

class DeleteStudentRequest extends FormRequest
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
            // No additional rules needed for delete, validation is in withValidator
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $studentId = $this->route('student');
            $parentId = auth()->user()->id;
            
            // Check if student exists and belongs to authenticated parent
            $student = StudentDetail::where('id', $studentId)
                                  ->where('parent_id', $parentId)
                                  ->first();
            
            if (!$student) {
                $validator->errors()->add('student', 'Student not found or you do not have permission to delete this student.');
                return;
            }

            // Check if student exists (has user record)
            if (!$student->student) {
                $validator->errors()->add('student', 'Student user record not found.');
                return;
            }

            // Store student data for use in controller
            $this->merge(['validated_student' => $student]);
        });
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'student.required' => 'Student ID is required.',
        ];
    }
}
