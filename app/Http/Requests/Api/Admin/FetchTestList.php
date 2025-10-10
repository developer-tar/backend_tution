<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;

class FetchTestList extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {

        return true;
    }

    /**
     * Merge input parameter into validation data.
     */
    protected function prepareForValidation()
    {
        $this->merge([
            'school_id' => $this->input('acdemic_course_id'),
            'subject_id' => $this->input('assigned_weeks'),
            'test_id' => $this->input('test_id'),
            'question_id' => $this->input('question_id'),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'school_id' => ['nullable', 'integer', 'exists:acdemic_course,id'],
            'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
            'test_id' => ['nullable', 'integer', 'exists:course_tests,id'],
            'question_id' => ['nullable', 'integer', 'exists:course_questions,id'],
        ];
    }
}