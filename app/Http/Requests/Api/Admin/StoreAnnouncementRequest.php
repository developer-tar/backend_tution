<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreAnnouncementRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'content' => ['nullable', 'string'],
            'priority' => ['nullable', 'string', 'in:general,important,urgent,maintenance'],
            'status' => ['nullable', 'integer', 'in:1,2,3'], // 1=Pending, 2=Approved, 3=Rejected
            'start_date_time' => ['required', 'date'],
            'end_date_time' => ['required', 'date', 'after:published_at'],
            'target_audience' => ['required', 'array'],
            'target_audience.*' => ['string', 'in:admin,student,parent,tutor,school'],
            'class_ids' => ['required', 'array'],
            'class_ids.*' => ['integer', 'exists:years,id'],
            'is_pinned' => ['nullable', 'boolean'],
            'announcement_image' => ['nullable', 'image', 'max:10240'], // Max 10MB - single image
            'announcement_images' => ['nullable', 'array'], // Multiple images
            'announcement_images.*' => ['image', 'max:10240'], // Max 10MB per image
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Announcement title is required.',
            'title.max' => 'Announcement title must not exceed 255 characters.',
            'description.max' => 'Description must not exceed 500 characters.',
            'type.in' => 'Type must be one of: general, important, urgent, maintenance.',
            'status.in' => 'Status must be one of: 1 (Pending), 2 (Approved), 3 (Rejected).',
            'start_date_time.date' => 'Start date must be a valid date.',
            'start_date_time.required' => 'Start date required.',
            'end_date_time.date' => 'Expires date must be a valid date.',
            'end_date_time.after' => 'Expires date must be after start date.',
            'target_audience.array' => 'Target audiences must be an array.',
            'target_audience.*.in' => 'Each target audience must be one of: Admin, Student, Parent, Tutor, School.',
            'class_ids.array' => 'Classes must be an array.',
            'class_ids.*.integer' => 'Each Class must be an integer.',
            'class_ids.*.exists' => 'One or more selected classes do not exist.',
            'is_pinned.boolean' => 'Is pinned must be a boolean value.',
            'announcement_image.image' => 'The uploaded file must be an image.',
            'announcement_image.max' => 'Announcement image size must not exceed 10MB.',
            'announcement_images.array' => 'Announcement images must be an array.',
            'announcement_images.*.image' => 'Each uploaded file must be an image.',
            'announcement_images.*.max' => 'Each announcement image size must not exceed 10MB.',
        ];
    }
}
