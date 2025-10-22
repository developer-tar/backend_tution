<?php

namespace App\Http\Requests\Api\Parent;

use App\Models\StudentDetail;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStudentRequest extends FormRequest
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
        $studentId = $this->route('student');
        
        return [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                Rule::unique('users', 'email')->ignore($this->getStudentUserId($studentId))
            ],
            'year_id' => 'required|integer|exists:years,id',
            'month_id' => 'required|integer|exists:months,id',
            'day_id' => 'required|integer|exists:days,id',
            'region_id' => 'required|integer|exists:regions,id',
            'gender_id' => 'required|integer|exists:genders,id',
            'target_school_id' => 'required|integer|exists:target_schools,id',
            'display_name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('student_details', 'display_name')->ignore($studentId)
            ],
            'show_answer_after_n_attempts' => 'nullable|integer|min:1|max:4',
            'allow_view_examiner_report_for_mocks' => 'nullable|boolean',
            'can_change_password' => 'nullable|boolean',
            'bio' => 'nullable|string',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'first_name.required' => 'First name is required.',
            'last_name.required' => 'Last name is required.',
            'email.required' => 'Email is required.',
            'email.email' => 'Please provide a valid email address.',
            'email.unique' => 'This email is already taken.',
            'year_id.required' => 'Year is required.',
            'year_id.exists' => 'Selected year is invalid.',
            'month_id.required' => 'Month is required.',
            'month_id.exists' => 'Selected month is invalid.',
            'day_id.required' => 'Day is required.',
            'day_id.exists' => 'Selected day is invalid.',
            'region_id.required' => 'Region is required.',
            'region_id.exists' => 'Selected region is invalid.',
            'gender_id.required' => 'Gender is required.',
            'gender_id.exists' => 'Selected gender is invalid.',
            'target_school_id.required' => 'Target school is required.',
            'target_school_id.exists' => 'Selected target school is invalid.',
            'display_name.required' => 'Display name is required.',
            'display_name.unique' => 'This display name is already taken.',
            'show_answer_after_n_attempts.min' => 'Show answer attempts must be at least 1.',
            'show_answer_after_n_attempts.max' => 'Show answer attempts cannot exceed 4.',
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
            
            // Check if student exists and belongs to authenticated parent
            $student = StudentDetail::where('id', $studentId)
                                  ->where('parent_id', auth()->user()->id)
                                  ->first();
            
            if (!$student) {
                $validator->errors()->add('student', 'Student not found or you do not have permission to edit this student.');
            }
        });
    }

    /**
     * Get the student's user ID for email uniqueness validation
     */
    private function getStudentUserId($studentId)
    {
        $student = StudentDetail::find($studentId);
        return $student ? $student->child_id : null;
    }
}
