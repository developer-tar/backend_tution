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
            'message' => ['nullable', 'string'],
            'priority' => ['nullable', 'string', 'in:general,important,urgent,maintenance'],
            'status' => ['nullable', 'integer', 'in:1,2,3'], // 1=Pending, 2=Approved, 3=Rejected
            'start_date_time' => ['required', 'date'],
            'end_date_time' => ['required', 'date', 'after:start_date_time'],
            'target_audience' => ['required', 'array', 'min:1'],
            'target_audience.*' => ['string', 'in:admin,student,parent,tutor,school'],
            // 'class_ids' => ['nullable', 'array'],
            // 'class_ids.*' => ['nullable', 'string'],
            'course_time_slot_ids' => ['nullable', 'array'],
            'course_time_slot_ids.*' => ['nullable', 'integer', 'exists:course_time_slots,id'],
            'is_pinned' => ['nullable', 'boolean'],
            'announcement_image' => ['nullable', 'image', 'max:10240'], // Max 10MB - single image
            'announcement_images' => ['nullable', 'array'], // Multiple images
            'announcement_images.*' => ['image', 'max:10240'], // Max 10MB per image
            'announcement_pdf' => ['nullable', 'mimes:pdf', 'max:10240'], // Max 10MB - single PDF
            'announcement_pdfs' => ['nullable', 'array'], // Multiple PDFs
            'announcement_pdfs.*' => ['mimes:pdf', 'max:10240'], // Max 10MB per PDF
            'announcement_timetable' => ['nullable', 'mimes:pdf,doc,docx,xls,xlsx', 'max:10240'], // Max 10MB - single timetable
            'announcement_timetables' => ['nullable', 'array'], // Multiple timetables
            'announcement_timetables.*' => ['mimes:pdf,doc,docx,xls,xlsx', 'max:10240'], // Max 10MB per timetable
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
            'class_ids.*.string' => 'Each Class name must be a string.',
            'course_time_slot_ids.array' => 'Course time slots must be an array.',
            'course_time_slot_ids.*.integer' => 'Each course time slot must be an integer.',
            'course_time_slot_ids.*.exists' => 'One or more selected course time slots do not exist.',
            'is_pinned.boolean' => 'Is pinned must be a boolean value.',
            'announcement_image.image' => 'The uploaded file must be an image.',
            'announcement_image.max' => 'Announcement image size must not exceed 10MB.',
            'announcement_images.array' => 'Announcement images must be an array.',
            'announcement_images.*.image' => 'Each uploaded file must be an image.',
            'announcement_images.*.max' => 'Each announcement image size must not exceed 10MB.',
            'announcement_pdf.mimes' => 'The uploaded file must be a PDF.',
            'announcement_pdf.max' => 'PDF size must not exceed 10MB.',
            'announcement_pdfs.array' => 'PDFs must be an array.',
            'announcement_pdfs.*.mimes' => 'Each uploaded file must be a PDF.',
            'announcement_pdfs.*.max' => 'Each PDF size must not exceed 10MB.',
            'announcement_timetable.mimes' => 'The uploaded file must be PDF, DOC, DOCX, XLS, or XLSX.',
            'announcement_timetable.max' => 'Timetable size must not exceed 10MB.',
            'announcement_timetables.array' => 'Timetables must be an array.',
            'announcement_timetables.*.mimes' => 'Each timetable must be PDF, DOC, DOCX, XLS, or XLSX.',
            'announcement_timetables.*.max' => 'Each timetable size must not exceed 10MB.',
        ];
    }
}
