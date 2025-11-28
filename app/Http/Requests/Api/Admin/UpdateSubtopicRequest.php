<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSubtopicRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'course_topic_id' => ['sometimes', 'required', 'integer', 'exists:course_topics,id'],
            'subtopic_name' => ['sometimes', 'required', 'string'],
            'content_upload' => ['nullable', 'array', 'max:10'],
            'content_upload.*' => ['file', 'mimes:mp4,mov,avi,wmv,pdf,jpg,jpeg,png,mpeg', 'max:512000'],
        ];
    }

    public function messages(): array
    {
        return [
            'course_topic_id.required' => 'Course topic is required.',
            'course_topic_id.integer' => 'Course topic ID must be an integer.',
            'course_topic_id.exists' => 'The selected course topic does not exist.',
            'subtopic_name.required' => 'Subtopic name is required.',
            'subtopic_name.string' => 'Subtopic name must be a string.',
            'content_upload.array' => 'Content upload must be an array of files.',
            'content_upload.max' => 'Maximum 10 files can be uploaded at once.',
            'content_upload.*.file' => 'Each content item must be a valid file.',
            'content_upload.*.mimes' => 'Content files must be one of: mp4, mov, avi, wmv, pdf, jpg, jpeg, png, mpeg.',
            'content_upload.*.max' => 'Each content file must not exceed 50MB (512000 KB).',
        ];
    }
}

