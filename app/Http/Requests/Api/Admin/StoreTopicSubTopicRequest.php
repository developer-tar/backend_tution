<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreTopicSubTopicRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'course_assigment_id' => ['required', 'integer', 'exists:course_assignments,id'],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'topic_name' => ['required', 'string'],
            'subtopic_name' => ['nullable', 'string'],
            'content_upload' => ['required', 'array'], // Make sure it's an array of files
            'content_upload.*' => ['file', 'mimes:mp4,mov,avi,wmv,pdf,jpg,jpeg,png', 'max:51200'], // Allow multiple types(50Mb)
        ];

    }
}
