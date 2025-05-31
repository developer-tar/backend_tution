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
            'acdemic_course_id' => $this->input('acdemic_course_id'),
            'subject_id' => $this->input('assigned_weeks'),
            'assignment_id' => $this->input('assignment_id'),
            'course_topic_id' => $this->input('course_topic_id'),
            'course_subtopic_id' => $this->input('course_subtopic_id'),
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
            'acdemic_course_id' => ['nullable', 'integer', 'exists:acdemic_course,id'],
            'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
            'assignment_id' => ['nullable', 'integer', 'exists:course_assignments,id'],
            'course_topic_id' => ['nullable', 'integer', 'exists:course_topics,id'],
            'course_subtopic_id' => ['nullable', 'integer', 'exists:course_sub_topics,id'],
            'test_id' => ['nullable', 'integer', 'exists:course_tests,id'],
            'question_id' => ['nullable', 'integer', 'exists:course_questions,id'],
        ];
    }
}