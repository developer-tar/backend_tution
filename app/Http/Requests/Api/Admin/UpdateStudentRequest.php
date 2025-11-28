<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Contracts\Validation\Validator;
use App\Models\StudentDetail;

class UpdateStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $studentId = $this->route('student');
        
        // Find student by StudentDetail ID or User ID
        $studentDetail = StudentDetail::find($studentId);
        if (!$studentDetail) {
            $studentDetail = StudentDetail::where('child_id', $studentId)->first();
        }
        
        $studentUserId = $studentDetail ? $studentDetail->child_id : null;

        return [
            'first_name' => ['sometimes', 'required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', Rule::unique('users', 'email')->ignore($studentUserId)],
            'password' => ['nullable', 'string', 'min:8', 'max:10'],
            'year_id' => ['sometimes', 'required', 'integer', 'exists:years,id'],
            'month_id' => ['sometimes', 'required', 'integer', 'exists:months,id'],
            'day_id' => ['sometimes', 'required', 'integer', 'exists:days,id'],
            'region_id' => ['sometimes', 'required', 'integer', 'exists:regions,id'],
            'gender_id' => ['sometimes', 'required', 'integer', 'exists:genders,id'],
            'target_school_id' => ['sometimes', 'required', 'integer', 'exists:target_schools,id'],
            'display_name' => ['sometimes', 'required', 'string', 'max:100', Rule::unique('student_details', 'display_name')->ignore($studentDetail ? $studentDetail->id : null)],
            'show_answer_after_n_attempts' => ['nullable', 'integer', 'min:1', 'max:4'],
            'allow_view_examiner_report_for_mocks' => ['nullable', 'boolean'],
            'can_change_password' => ['nullable', 'boolean'],
            'bio' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required' => 'First name is required.',
            'first_name.string' => 'First name must be a string.',
            'first_name.max' => 'First name must not exceed 255 characters.',
            'last_name.string' => 'Last name must be a string.',
            'last_name.max' => 'Last name must not exceed 255 characters.',
            'email.required' => 'Email is required.',
            'email.email' => 'Email must be a valid email address.',
            'email.unique' => 'This email is already taken.',
            'password.string' => 'Password must be a string.',
            'password.min' => 'Password must be at least 8 characters.',
            'password.max' => 'Password must not exceed 10 characters.',
            'year_id.required' => 'Year is required.',
            'year_id.integer' => 'Year ID must be an integer.',
            'year_id.exists' => 'The selected year does not exist.',
            'month_id.required' => 'Month is required.',
            'month_id.integer' => 'Month ID must be an integer.',
            'month_id.exists' => 'The selected month does not exist.',
            'day_id.required' => 'Day is required.',
            'day_id.integer' => 'Day ID must be an integer.',
            'day_id.exists' => 'The selected day does not exist.',
            'region_id.required' => 'Region is required.',
            'region_id.integer' => 'Region ID must be an integer.',
            'region_id.exists' => 'The selected region does not exist.',
            'gender_id.required' => 'Gender is required.',
            'gender_id.integer' => 'Gender ID must be an integer.',
            'gender_id.exists' => 'The selected gender does not exist.',
            'target_school_id.required' => 'Target school is required.',
            'target_school_id.integer' => 'Target school ID must be an integer.',
            'target_school_id.exists' => 'The selected target school does not exist.',
            'display_name.required' => 'Display name is required.',
            'display_name.string' => 'Display name must be a string.',
            'display_name.max' => 'Display name must not exceed 100 characters.',
            'display_name.unique' => 'This display name is already taken.',
            'show_answer_after_n_attempts.integer' => 'Show answer after N attempts must be an integer.',
            'show_answer_after_n_attempts.min' => 'Show answer after N attempts must be at least 1.',
            'show_answer_after_n_attempts.max' => 'Show answer after N attempts must not exceed 4.',
            'allow_view_examiner_report_for_mocks.boolean' => 'Allow view examiner report for mocks must be a boolean.',
            'can_change_password.boolean' => 'Can change password must be a boolean.',
            'bio.string' => 'Bio must be a string.',
        ];
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
            }
        });
    }
}



