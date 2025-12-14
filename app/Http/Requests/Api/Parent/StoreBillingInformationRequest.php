<?php

namespace App\Http\Requests\Api\Parent;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreBillingInformationRequest extends FormRequest
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
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            // At least one address field should be present
            $hasAddress = !empty($this->input('address_line1')) ||
                         !empty($this->input('address_line2')) ||
                         !empty($this->input('city')) ||
                         !empty($this->input('state')) ||
                         !empty($this->input('postal_code')) ||
                         !empty($this->input('country'));

            if (!$hasAddress) {
                $validator->errors()->add('address', 'At least one address field is required.');
            }
        });
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
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
