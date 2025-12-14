<?php

namespace App\Http\Requests\Api\Parent;

use App\Models\BillingInformation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateBillingInformationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'id' => ['required', 'integer', 'exists:billing_informations,id'],
            'parent_id' => ['required', 'integer', 'exists:users,id'],
            'address_line1' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address_line2' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'state' => ['sometimes', 'nullable', 'string', 'max:100'],
            'postal_code' => ['sometimes', 'nullable', 'string', 'max:20'],
            'country' => ['sometimes', 'nullable', 'string', 'max:100'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $parentId = auth()->user()->id;
            $billingInfoId = $this->input('id');
            $requestParentId = $this->input('parent_id');

            // Validate that parent_id matches authenticated user
            if ($requestParentId != $parentId) {
                $validator->errors()->add('parent_id', 'Parent ID does not match authenticated user.');
                return;
            }

            // Validate that billing information belongs to authenticated parent
            $billingInfo = BillingInformation::where('id', $billingInfoId)
                ->where('parent_id', $parentId)
                ->first();

            if (!$billingInfo) {
                $validator->errors()->add('id', 'Billing information not found or does not belong to you.');
                return;
            }
        });
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'id.required' => 'Billing information ID is required.',
            'id.integer' => 'Billing information ID must be a valid number.',
            'id.exists' => 'Billing information not found.',
            'parent_id.required' => 'Parent ID is required.',
            'parent_id.integer' => 'Parent ID must be a valid number.',
            'parent_id.exists' => 'Parent does not exist.',
            'address_line1.string' => 'Address line 1 must be a string.',
            'address_line1.max' => 'Address line 1 cannot exceed 255 characters.',
            'address_line2.string' => 'Address line 2 must be a string.',
            'address_line2.max' => 'Address line 2 cannot exceed 255 characters.',
            'city.string' => 'City must be a string.',
            'city.max' => 'City cannot exceed 100 characters.',
            'state.string' => 'State must be a string.',
            'state.max' => 'State cannot exceed 100 characters.',
            'postal_code.string' => 'Postal code must be a string.',
            'postal_code.max' => 'Postal code cannot exceed 20 characters.',
            'country.string' => 'Country must be a string.',
            'country.max' => 'Country cannot exceed 100 characters.',
            'phone.string' => 'Phone must be a string.',
            'phone.max' => 'Phone cannot exceed 20 characters.',
        ];
    }
}
