<?php

namespace App\Http\Requests\MasterForm;

class SchoolRequest extends BaseMasterFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $rules = [
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'logo' => 'nullable|string|max:255',
            'website' => 'nullable|url|max:255',
            'status' => 'nullable|string|in:active,inactive',
        ];

        // For updates, make fields optional
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules = [
                'name' => 'sometimes|string|max:255',
                'address' => 'nullable|string|max:255',
                'phone' => 'nullable|string|max:255',
                'email' => 'nullable|email|max:255',
                'logo' => 'nullable|string|max:255',
                'website' => 'nullable|url|max:255',
                'status' => 'nullable|string|in:active,inactive',
            ];
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'email.email' => 'The email must be a valid email address.',
            'website.url' => 'The website must be a valid URL.',
            'status.in' => 'The status must be either active or inactive.',
        ]);
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return array_merge(parent::attributes(), [
            'name' => 'school name',
            'address' => 'school address',
            'phone' => 'phone number',
            'email' => 'email address',
            'logo' => 'logo path',
            'website' => 'website URL',
            'status' => 'school status',
        ]);
    }
}
