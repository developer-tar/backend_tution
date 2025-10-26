<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ContentRequest extends FormRequest
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
            'course_id' => 'nullable|integer|exists:courses,id',
            'subject_id' => 'nullable|integer|exists:subjects,id',
            'topic_id' => 'nullable|integer|exists:course_topics,id',
            'subtopic_id' => 'nullable|integer|exists:course_sub_topics,id'
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
            'course_id.exists' => 'The selected course does not exist.',
            'subject_id.exists' => 'The selected subject does not exist.',
            'topic_id.exists' => 'The selected topic does not exist.',
            'subtopic_id.exists' => 'The selected subtopic does not exist.'
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array
     */
    public function attributes(): array
    {
        return [
            'course_id' => 'course',
            'subject_id' => 'subject',
            'topic_id' => 'topic',
            'subtopic_id' => 'subtopic'
        ];
    }
}
