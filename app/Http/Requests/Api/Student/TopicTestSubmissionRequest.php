<?php

namespace App\Http\Requests\Api\Student;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class TopicTestSubmissionRequest extends FormRequest
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
            'test_id' => $this->route('test_id'),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'test_id' => ['required', 'integer', 'exists:course_tests,id'],
            'answers' => ['required', 'array', 'min:1'],
            'answers.*.question_id' => ['required', 'integer', 'exists:course_questions,id'],
            'answers.*.option_id' => ['required', 'integer', 'exists:course_options,id'],
            'answers.*.time_taken' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'answers.required' => 'Test answers are required.',
            'answers.array' => 'Answers must be an array.',
            'answers.min' => 'At least one answer is required.',
            'answers.*.question_id.required' => 'Question ID is required for each answer.',
            'answers.*.question_id.exists' => 'Invalid question ID provided.',
            'answers.*.option_id.required' => 'Option ID is required for each answer.',
            'answers.*.option_id.exists' => 'Invalid option ID provided.',
            'answers.*.time_taken.required' => 'Time taken is required for each answer.',
            'answers.*.time_taken.min' => 'Time taken must be at least 1 second.',
        ];
    }
}
