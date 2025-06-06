<?php

namespace App\Http\Requests\Api\Student;

use Illuminate\Foundation\Http\FormRequest;

class CAssignmentRequest extends FormRequest {
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array {
        
        return [
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'choose_title' => ['required', 'string', 'in:TopicContent,SubTopicContent,TopicTest,SubTopicTest'],
        ];
    }
}
