<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTopicRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'course_assigment_id' => ['sometimes', 'required', 'integer', 'exists:course_assignments,id'],
            'subject_id' => ['sometimes', 'required', 'integer', 'exists:subjects,id'],
            'topic_name' => ['sometimes', 'required', 'string'],
            'content_upload' => ['nullable', 'array', 'max:10'],
            'content_upload.*' => ['file', 'mimes:mp4,mov,avi,wmv,pdf,jpg,jpeg,png,mpeg', 'max:512000'],
        ];
    }

    public function messages(): array
    {
        return [
            'course_assigment_id.required' => 'Course assignment is required.',
            'course_assigment_id.integer' => 'Course assignment ID must be an integer.',
            'course_assigment_id.exists' => 'The selected course assignment does not exist.',
            'subject_id.required' => 'Subject is required.',
            'subject_id.integer' => 'Subject ID must be an integer.',
            'subject_id.exists' => 'The selected subject does not exist.',
            'topic_name.required' => 'Topic name is required.',
            'topic_name.string' => 'Topic name must be a string.',
            'content_upload.array' => 'Content upload must be an array of files.',
            'content_upload.max' => 'Maximum 10 files can be uploaded at once.',
            'content_upload.*.file' => 'Each content item must be a valid file.',
            'content_upload.*.mimes' => 'Content files must be one of: mp4, mov, avi, wmv, pdf, jpg, jpeg, png, mpeg.',
            'content_upload.*.max' => 'Each content file must not exceed 50MB (512000 KB).',
        ];
    }
}

