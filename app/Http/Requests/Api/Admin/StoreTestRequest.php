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
            'subtopic_id'           => ['required', 'integer', 'exists:course_sub_topics,id'],

            'questions'             => ['required', 'array'],
            'questions.*'           => ['required', 'string', 'min:10','max:255'],

            'options'               => ['required', 'array' ],
            'options.*'             => ['required', 'array', 'min:2'], // At least 2 options per question
            'options.*.*'           => ['required', 'string', 'min:1','max:255'],

            'answers'               => ['required', 'array', 'max:1'],
            'answers.*'             => ['required', 'string',  'min:1','max:255'],

            'duration_in_sec'       => ['required', 'array', 'max:1'],// max 1 duration per question
            'duration_in_sec.*'     => ['required', 'integer', 'min:1', 'max:3'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $options = $this->input('options', []);
            $answers = $this->input('answers', []);

            foreach ($answers as $index => $answer) {
                if (!isset($options[$index]) || !in_array($answer, $options[$index])) {
                    $validator->errors()->add("answers.$index", "The answer must match one of the provided options.");
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'questions.*.required' => 'Each question is required.',
            'options.*.min' => 'Each question must have at least two options.',
            'options.*.*.required' => 'Each option must be a non-empty string.',
            'answers.*.required' => 'Each question must have an answer.',
            'answers.*.max' => 'Each question must have at only one answers.',
            'duration_in_sec.*.required' => 'Duration is required for each question.',
        ];
    }
}
