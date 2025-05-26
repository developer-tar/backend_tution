<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreTestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'topic_id'              => ['required', 'integer', 'exists:course_topics,id'],
            'subtopic_id'           => ['nullable', 'integer', 'exists:course_sub_topics,id'],

            'questions'             => ['required', 'array'],
            'questions.*'           => ['required', 'string', 'min:10', 'max:255'],

            'options'               => ['required', 'array'],
            'options.*'             => ['required', 'array', 'min:2'],
            'options.*.*'           => ['required', 'string', 'min:1', 'max:255'],

            'answers'               => ['required', 'array'],
            'answers.*'             => ['required', 'string', 'min:1', 'max:255'],

            'duration_in_sec'       => ['required', 'array'],
            'duration_in_sec.*'     => ['required', 'integer', 'min:1'],
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

            if (
                count($answers) !== $count ||
                count($options) !== $count ||
                count($durations) !== $count
            ) {
                $validator->errors()->add('questions', 'The number of questions, options, answers, and durations must match.');
            }

            foreach ($answers as $index => $answer) {
                if (!isset($options[$index]) || !in_array($answer, $options[$index], true)) {
                    $validator->errors()->add("answers.$index", "The answer must match one of the options for question #$index.");
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'questions.required' => 'Questions are required.',
            'questions.*.required' => 'Each question is required.',
            'options.required' => 'Options are required.',
            'options.*.required' => 'Each question must have options.',
            'options.*.min' => 'Each question must have at least two options.',
            'options.*.*.required' => 'Each option must be a non-empty string.',
            'answers.required' => 'Answers are required.',
            'answers.*.required' => 'Each question must have one answer.',
            'duration_in_sec.required' => 'Duration is required for each question.',
            'duration_in_sec.*.required' => 'Each question must have a duration.',
        ];
    }
}
