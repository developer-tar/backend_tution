<?php

namespace App\Http\Requests\Api\Student;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MarkCompletionRequest extends FormRequest
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
            'model_type' => [
                'required',
                'string',
                Rule::in(['TopicContent', 'SubTopicContent'])
            ],
            'model_id' => [
                'required',
                'integer',
                'min:1'
            ]
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
            'model_type.required' => 'Model type is required.',
            'model_type.in' => 'Model type must be either TopicContent or SubTopicContent.',
            'model_id.required' => 'Model ID is required.',
            'model_id.integer' => 'Model ID must be a valid integer.',
            'model_id.min' => 'Model ID must be greater than 0.',
        ];
    }
}
