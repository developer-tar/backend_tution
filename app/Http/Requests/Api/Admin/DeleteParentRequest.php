<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use App\Models\User;
use App\Models\StudentDetail;

class DeleteParentRequest extends FormRequest
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



