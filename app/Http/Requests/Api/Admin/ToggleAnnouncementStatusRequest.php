<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ToggleAnnouncementStatusRequest extends FormRequest
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
            'status' => ['required', 'integer', 'in:1,2,3'], // 1=Pending, 2=Approved, 3=Rejected
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Status is required.',
            'status.in' => 'Status must be one of: 1 (Pending), 2 (Approved), 3 (Rejected).',
        ];
    }
}



