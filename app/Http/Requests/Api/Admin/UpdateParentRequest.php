<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Contracts\Validation\Validator;
use App\Models\User;
use App\Models\StudentDetail;

class UpdateParentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $parentId = $this->route('parent');
        
        return [
            'first_name' => ['sometimes', 'required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', Rule::unique('users', 'email')->ignore($parentId)],
            'password' => ['nullable', 'string', 'min:8', 'max:10'],
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
        ];
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $parentId = $this->route('parent');
            
            // Check if parent exists
            $parent = User::find($parentId);
            
            if (!$parent) {
                $validator->errors()->add('parent', 'The parent does not exist.');
                return;
            }

            // Check if user has Parent role
            $hasParentRole = $parent->roles()->where('name', config('constants.roles.PARENT'))->exists();
            
            if (!$hasParentRole) {
                $validator->errors()->add('parent', 'User does not have Parent role.');
                return;
            }

            // Check if parent has students
            $hasStudents = StudentDetail::where('parent_id', $parentId)->exists();
            
            if (!$hasStudents) {
                $validator->errors()->add('parent', 'Parent does not have any students.');
            }
        });
    }
}



