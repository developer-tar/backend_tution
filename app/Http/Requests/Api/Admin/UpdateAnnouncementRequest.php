<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Role;

class UpdateAnnouncementRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $announcementId = $this->route('announcement') ?? $this->route('id');

        if ($announcementId) {
            $this->merge([
                'announcement' => $announcementId,
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Get admin role ID to exclude it
        $adminRole = Role::where('name', config('constants.roles.ADMIN'))->first();
        $adminRoleId = $adminRole ? $adminRole->id : null;

        return [
            'announcement' => [
                'required',
                'integer',
                Rule::exists('announcements', 'id'),
            ],
            'title' => 'sometimes|required|string|max:255',
            'message' => 'sometimes|required|string',
            'status' => 'sometimes|required|in:' . config('constants.announcement_status.draft') . ',' . config('constants.announcement_status.publish'),
            'target_audience' => [
                'sometimes',
                'required',
                'array',
                'min:1',
                function ($attribute, $value, $fail) use ($adminRoleId) {
                    if ($adminRoleId && in_array($adminRoleId, $value)) {
                        $fail('The Admin role cannot be included in the target audience.');
                    }
                },
            ],
            'target_audience.*' => [
                Rule::exists('roles', 'id'),
                function ($attribute, $value, $fail) use ($adminRoleId) {
                    if ($adminRoleId && $value == $adminRoleId) {
                        $fail('The Admin role cannot be included in the target audience.');
                    }
                },
            ],
            'module_id' => ['nullable', Rule::exists('module', 'id')],
            'academic_year_id' => ['nullable', Rule::exists('acdemic_years', 'id')],
            'course_id' => ['nullable', Rule::exists('courses', 'id')],
            'class_id' => ['nullable', Rule::exists('course_time_slots', 'id')],
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
            'announcement.required' => 'The announcement ID is required.',
            'announcement.integer' => 'The announcement ID must be an integer.',
            'announcement.exists' => 'The selected announcement does not exist.',
            'title.required' => 'The title field is required.',
            'title.string' => 'The title must be a string.',
            'title.max' => 'The title may not be greater than :max characters.',
            'message.required' => 'The message field is required.',
            'message.string' => 'The message must be a string.',
            'status.required' => 'The status field is required.',
            'status.in' => 'The status must be either draft or publish.',
            'target_audience.required' => 'The target audience field is required.',
            'target_audience.array' => 'The target audience must be an array.',
            'target_audience.min' => 'The target audience must have at least :min item(s).',
            'target_audience.*.exists' => 'One or more selected roles do not exist.',
            'module_id.exists' => 'The selected module does not exist.',
            'academic_year_id.exists' => 'The selected academic year does not exist.',
            'course_id.exists' => 'The selected course does not exist.',
            'class_id.exists' => 'The selected class does not exist.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array
     */
    public function attributes(): array
    {
        return [
            'announcement' => 'announcement',
            'title' => 'title',
            'message' => 'message',
            'status' => 'status',
            'target_audience' => 'target audience',
            'module_id' => 'module',
            'academic_year_id' => 'academic year',
            'course_id' => 'course',
            'class_id' => 'class',
        ];
    }
}
