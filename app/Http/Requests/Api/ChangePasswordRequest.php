<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;

class ChangePasswordRequest extends FormRequest
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
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|max:10',
            'new_password_confirmation' => 'required|string|same:new_password',
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
            $user = auth()->user();

            if (!$user) {
                $validator->errors()->add('user', 'User not authenticated.');
                return;
            }

            // Check current password
            if (!Hash::check($this->input('current_password'), $user->password)) {
                $validator->errors()->add('current_password', 'Current password is incorrect.');
            }
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
            'current_password.required' => 'Current password is required.',
            'new_password.required' => 'New password is required.',
            'new_password.min' => 'New password must be at least 8 characters.',
            'new_password.max' => 'New password must not exceed 10 characters.',
            'new_password_confirmation.required' => 'Password confirmation is required.',
            'new_password_confirmation.same' => 'New password and confirmation password do not match.',
        ];
    }
}
