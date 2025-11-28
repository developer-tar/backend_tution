<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateTestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'topic_id'              => ['sometimes', 'required', 'integer', 'exists:course_topics,id'],
            'subtopic_id'           => ['nullable', 'integer', 'exists:course_sub_topics,id'],

            'questions'             => ['sometimes', 'required', 'array'],
            'questions.*'           => ['required', 'string', 'min:10', 'max:255'],

            'options'               => ['sometimes', 'required', 'array'],
            'options.*'             => ['required', 'array', 'min:2'],
            'options.*.*'           => ['required', 'string', 'min:1', 'max:255'],

            'answers'               => ['sometimes', 'required', 'array'],
            'answers.*'             => ['required', 'string', 'min:1', 'max:255'],

            'duration_in_sec'       => ['sometimes', 'required', 'array'],
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

            // Only validate if questions are provided (for partial updates)
            if (!empty($questions)) {
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
                        $validator->errors()->add("answers.$index", "The answer must match one of the options for question #" . ($index + 1) . ".");
                    }
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'topic_id.required' => 'Topic is required.',
            'topic_id.integer' => 'Topic ID must be an integer.',
            'topic_id.exists' => 'The selected topic does not exist.',
            'subtopic_id.integer' => 'Subtopic ID must be an integer.',
            'subtopic_id.exists' => 'The selected subtopic does not exist.',
            'questions.required' => 'Questions are required.',
            'questions.array' => 'Questions must be provided as an array.',
            'questions.*.required' => 'Each question is required.',
            'questions.*.string' => 'Each question must be a string.',
            'questions.*.min' => 'Each question must be at least 10 characters long.',
            'questions.*.max' => 'Each question must not exceed 255 characters.',
            'options.required' => 'Options are required.',
            'options.array' => 'Options must be provided as an array.',
            'options.*.required' => 'Each question must have options.',
            'options.*.array' => 'Options for each question must be an array.',
            'options.*.min' => 'Each question must have at least two options.',
            'options.*.*.required' => 'Each option must be a non-empty string.',
            'options.*.*.string' => 'Each option must be a string.',
            'options.*.*.min' => 'Each option must be at least 1 character long.',
            'options.*.*.max' => 'Each option must not exceed 255 characters.',
            'answers.required' => 'Answers are required.',
            'answers.array' => 'Answers must be provided as an array.',
            'answers.*.required' => 'Each question must have one answer.',
            'answers.*.string' => 'Each answer must be a string.',
            'answers.*.min' => 'Each answer must be at least 1 character long.',
            'answers.*.max' => 'Each answer must not exceed 255 characters.',
            'duration_in_sec.required' => 'Duration is required for each question.',
            'duration_in_sec.array' => 'Durations must be provided as an array.',
            'duration_in_sec.*.required' => 'Each question must have a duration.',
            'duration_in_sec.*.integer' => 'Each duration must be an integer.',
            'duration_in_sec.*.min' => 'Each duration must be at least 1 second.',
        ];
    }
}

