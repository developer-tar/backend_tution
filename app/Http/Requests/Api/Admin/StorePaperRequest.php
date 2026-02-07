<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StorePaperRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Check if user is authenticated
        $user = $this->user();
        if (!$user) {
            return false;
        }
        
        // Check if user has admin role using constants
        return $user->roles()
            ->where('name', config('constants.roles.ADMIN'))
            ->exists();
    }

    public function rules(): array
    {
        return [
            'name'              => ['required', 'string', 'max:255'],
            'description'       => ['nullable', 'string'],
            'category_id'       => ['required', 'integer', 'exists:mock_exam_categories,id'],
            'format_id'         => ['required', 'integer', 'exists:formats,id'],
            'price'             => ['required', 'numeric', 'min:0'],
            'currency'          => ['nullable', 'string', 'size:1', 'in:€,$,£'],
            'duration_minutes'  => ['nullable', 'integer', 'min:1'],
            'school_id'         => ['nullable', 'integer', 'exists:schools,id'],

            'paper_image'       => ['nullable', 'image', 'max:10240'],

            // Manual create paper: questions/options/answers
            'questions'         => ['required', 'array', 'min:1'],
            'questions.*'       => ['required', 'string', 'min:1', 'max:2000'],
            'options'           => ['required', 'array'],
            'options.*'         => ['required', 'array', 'min:2'],
            'options.*.*'       => ['required', 'string', 'min:1', 'max:255'],
            'answers'           => ['required', 'array'],
            'answers.*'         => ['required', 'string', 'min:1', 'max:255'],
            'duration_in_sec'   => ['required', 'array'],
            'duration_in_sec.*' => ['required', 'integer', 'min:1'],
            'marks'             => ['nullable', 'array'],
            'marks.*'           => ['nullable', 'integer', 'min:1'],
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
                    $validator->errors()->add("answers.$index", "The answer must match one of the options for question #" . ($index + 1) . ".");
                }
            }

            $marks = $this->input('marks', []);
            if (!empty($marks) && count($marks) !== $count) {
                $validator->errors()->add('marks', 'The number of marks must match the number of questions.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Paper name is required.',
            'category_id.required' => 'Category is required.',
            'category_id.exists' => 'Selected category does not exist.',
            'format_id.required' => 'Format is required.',
            'format_id.exists' => 'Selected format does not exist.',
            'price.required' => 'Price is required.',
            'price.min' => 'Price must be at least 0.',
            'school_id.exists' => 'Selected school does not exist.',
            'paper_image.image' => 'The uploaded file must be an image.',
            'paper_image.max' => 'Paper image size must not exceed 10MB.',
            'questions.required' => 'At least one question is required.',
            'questions.min' => 'At least one question is required.',
            'options.*.min' => 'Each question must have at least two options.',
            'duration_in_sec.required' => 'Duration is required for each question.',
        ];
    }
}







