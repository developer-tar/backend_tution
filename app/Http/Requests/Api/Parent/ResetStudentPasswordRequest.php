<?php

namespace App\Http\Requests\Api\Parent;

use App\Models\StudentDetail;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;

class ResetStudentPasswordRequest extends FormRequest
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
            'email_id' => 'required|email',
            'current_password' => 'required|string|min:6',
            'new_password' => 'required|string|min:6',
            'confirm_password' => 'required|string|same:new_password',
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
            $email = $this->input('email_id');
            $currentPassword = $this->input('current_password');
            $parentId = auth()->user()->id;
            
            // Find user by email
            $user = User::where('email', $email)->first();
            
            if (!$user) {
                $validator->errors()->add('email_id', 'Student with this email not found.');
                return;
            }

            // Check if this student belongs to authenticated parent
            $studentDetail = StudentDetail::where('child_id', $user->id)
                                        ->where('parent_id', $parentId)
                                        ->first();
            
            if (!$studentDetail) {
                $validator->errors()->add('email_id', 'This student does not belong to you or student not found.');
                return;
            }

            // Check if student can change password
            if (!$studentDetail->can_change_password) {
                $validator->errors()->add('student', 'Password change is not allowed for this student.');
                return;
            }

            // Check current password
            if (!Hash::check($currentPassword, $user->password)) {
                $validator->errors()->add('current_password', 'Current password is incorrect.');
                return;
            }

            // Store validated data for use in controller
            $this->merge([
                'validated_user' => $user,
                'validated_student_detail' => $studentDetail
            ]);
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
            'email_id.required' => 'Email ID is required.',
            'email_id.email' => 'Please provide a valid email address.',
            'current_password.required' => 'Current password is required.',
            'current_password.min' => 'Current password must be at least 6 characters.',
            'new_password.required' => 'New password is required.',
            'new_password.min' => 'New password must be at least 6 characters.',
            'confirm_password.required' => 'Password confirmation is required.',
            'confirm_password.same' => 'New password and confirm password do not match.',
        ];
    }
}
