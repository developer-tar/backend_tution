<?php

namespace App\Http\Requests\Api\Student;

use Illuminate\Foundation\Http\FormRequest;

class SubTopicIdRequest extends FormRequest
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
            'sub_topic_id' => $this->route('sub_topic_id'),
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
            'sub_topic_id' => ['required', 'integer', 'exists:course_sub_topics,id'],
        ];
    }
}
