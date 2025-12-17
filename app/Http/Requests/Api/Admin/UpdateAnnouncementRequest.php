<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAnnouncementRequest extends FormRequest
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
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'content' => ['nullable', 'string'],
            'type' => ['nullable', 'string', 'in:general,important,urgent,maintenance'],
            'status' => ['nullable', 'integer', 'in:1,2,3'], // 1=Pending, 2=Approved, 3=Rejected
            'published_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:published_at'],
            'target_roles' => ['nullable', 'array'],
            'target_roles.*' => ['string', 'in:Admin,Student,Parent,Tutor,School'],
            'target_years' => ['nullable', 'array'],
            'target_years.*' => ['integer', 'exists:years,id'],
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
            'published_at.date' => 'Published date must be a valid date.',
            'expires_at.date' => 'Expires date must be a valid date.',
            'expires_at.after' => 'Expires date must be after published date.',
            'target_roles.array' => 'Target roles must be an array.',
            'target_roles.*.in' => 'Each target role must be one of: Admin, Student, Parent, Tutor, School.',
            'target_years.array' => 'Target years must be an array.',
            'target_years.*.integer' => 'Each target year must be an integer.',
            'target_years.*.exists' => 'One or more selected years do not exist.',
            'is_pinned.boolean' => 'Is pinned must be a boolean value.',
            'announcement_image.image' => 'The uploaded file must be an image.',
            'announcement_image.max' => 'Announcement image size must not exceed 10MB.',
            'announcement_images.array' => 'Announcement images must be an array.',
            'announcement_images.*.image' => 'Each uploaded file must be an image.',
            'announcement_images.*.max' => 'Each announcement image size must not exceed 10MB.',
        ];
    }
}
