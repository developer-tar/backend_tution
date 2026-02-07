<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdatePaperRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'category_id' => ['sometimes', 'required', 'integer', 'exists:mock_exam_categories,id'],
            'format_id' => ['sometimes', 'required', 'integer', 'exists:formats,id'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'nullable', 'string', 'size:1', 'in:€,$,£'],
            'duration_minutes' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'school_id' => ['sometimes', 'nullable', 'integer', 'exists:schools,id'],
            'paper_image' => ['sometimes', 'nullable', 'image', 'max:10240'],

            // Optional: when updating questions (manual create paper flow)
            'questions' => ['sometimes', 'required', 'array', 'min:1'],
            'questions.*' => ['required', 'string', 'min:1', 'max:2000'],
            'options' => ['sometimes', 'required', 'array'],
            'options.*' => ['required', 'array', 'min:2'],
            'options.*.*' => ['required', 'string', 'min:1', 'max:255'],
            'answers' => ['sometimes', 'required', 'array'],
            'answers.*' => ['required', 'string', 'min:1', 'max:255'],
            'duration_in_sec' => ['sometimes', 'required', 'array'],
            'duration_in_sec.*' => ['required', 'integer', 'min:1'],
            'marks' => ['sometimes', 'nullable', 'array'],
            'marks.*' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            if ($this->has('questions') && is_array($this->input('questions')) && count($this->input('questions')) > 0) {
                $questions = $this->input('questions', []);
                $options = $this->input('options', []);
                $answers = $this->input('answers', []);
                $durations = $this->input('duration_in_sec', []);
                $count = count($questions);

                if (count($answers) !== $count || count($options) !== $count || count($durations) !== $count) {
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
        ];
    }
}
