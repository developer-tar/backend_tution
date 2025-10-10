<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreMockExamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'              => ['required', 'string', 'max:255'],
            'description'       => ['nullable', 'string'],
            'category_id'       => ['required', 'integer', 'exists:mock_exam_categories,id'],
            'format_id'            => ['required', 'string', 'exists:formats,id'],
            'price'             => ['required', 'numeric', 'min:0'],
            'currency'          => ['nullable', 'string', 'max:3'],
            'duration_minutes'  => ['nullable', 'integer', 'min:1'],
            'school_id'         => ['nullable', 'integer', 'exists:schools,id'],

            'questions'         => ['required', 'array', 'min:1'],
            'questions.*'       => ['required', 'string', 'min:10', 'max:500'],

            'options'           => ['required', 'array'],
            'options.*'         => ['required', 'array', 'min:2'],
            'options.*.*'       => ['required', 'string', 'min:1', 'max:255'],

            'answers'           => ['required', 'array'],
            'answers.*'         => ['required', 'string', 'min:1', 'max:255'],

            'duration_in_sec'   => ['required', 'array'],
            'duration_in_sec.*' => ['required', 'integer', 'min:1'],

            'marks'             => ['nullable', 'array'],
            'marks.*'           => ['nullable', 'integer', 'min:1'],
            'mock_exam_image'   => ['nullable', 'image', 'max:10240'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $questions = $this->input('questions', []);
            $options = $this->input('options', []);
            $answers = $this->input('answers', []);
            $durations = $this->input('duration_in_sec', []);

            $count = count($questions);

            // Check if all arrays have same count
            if (
                count($answers) !== $count ||
                count($options) !== $count ||
                count($durations) !== $count
            ) {
                $validator->errors()->add('questions', 'The number of questions, options, answers, and durations must match.');
            }

            // Validate each answer matches one of its options
            foreach ($answers as $index => $answer) {
                if (!isset($options[$index]) || !in_array($answer, $options[$index], true)) {
                    $validator->errors()->add("answers.$index", "The answer must match one of the options for question #" . ($index + 1) . ".");
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Mock exam name is required.',
            'category_id.required' => 'Category is required.',
            'category_id.exists' => 'Selected category does not exist.',
            'format_id.required' => 'Format is required.',
            'format_id.exists' => 'Selected format does not exist.',
            'price.required' => 'Price is required.',
            'price.min' => 'Price must be at least 0.',
            'school_id.exists' => 'Selected school does not exist.',

            'questions.required' => 'Questions are required.',
            'questions.min' => 'At least one question is required.',
            'questions.*.required' => 'Each question is required.',
            'questions.*.min' => 'Each question must be at least 10 characters.',

            'options.required' => 'Options are required.',
            'options.*.required' => 'Each question must have options.',
            'options.*.min' => 'Each question must have at least two options.',
            'options.*.*.required' => 'Each option must be a non-empty string.',

            'answers.required' => 'Answers are required.',
            'answers.*.required' => 'Each question must have one answer.',

            'duration_in_sec.required' => 'Duration is required for each question.',
            'duration_in_sec.*.required' => 'Each question must have a duration.',
            'duration_in_sec.*.integer' => 'Duration must be a number.',
            'duration_in_sec.*.min' => 'Duration must be at least 1 second.',

            'marks.*.integer' => 'Marks must be a number.',
            'marks.*.min' => 'Marks must be at least 1.',
            'mock_exam_image.image' => 'The uploaded file must be an image.',
            'mock_exam_image.max' => 'Course image size must not exceed 10MB.',
        ];
    }
}
