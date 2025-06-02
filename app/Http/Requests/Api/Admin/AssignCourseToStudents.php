<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AssignCourseToStudents extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'acdemic_course_id' => ['required', 'integer', 'exists:acdemic_course,id'],
            'student_id' => ['required', 'array'],
            'student_id.*' => ['required', 'integer', 'exists:users,id'],
        ];
    }

}
